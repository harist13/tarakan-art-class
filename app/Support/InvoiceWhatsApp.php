<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Support\Carbon;

/**
 * Menyusun tautan chat WhatsApp berisi rincian invoice untuk dikirim ke wali murid.
 *
 * Dipisah dari controller karena isi pesannya akan dipakai lagi begitu tautan
 * pembayaran (Midtrans Snap) ikut disertakan lewat $payUrl.
 *
 * Catatan: `students.phone_number` memang nomor wali (label formnya "No HP
 * Wali"), jadi tidak ada kolom nomor terpisah untuk orang tua.
 */
class InvoiceWhatsApp
{
    /** Tautan chat siap buka; null bila nomor wali murid tidak dapat dipakai. */
    public static function link(Payment $payment, ?string $payUrl = null): ?string
    {
        $number = $payment->student?->whatsappNumber();

        if ($number === null) {
            return null;
        }

        return self::chatUrl($number, self::message($payment, $payUrl));
    }

    /**
     * Tautan bagi ORANG TUA untuk mengirim bukti pembayaran ke WhatsApp admin
     * studio — arah sebaliknya dari link(), yang dipakai admin menagih.
     *
     * Berguna saat notifikasi gateway telat atau invoice dibayar di luar sistem:
     * orang tua punya cara langsung memberi kabar tanpa mengetik ulang apa pun.
     * Null bila nomor WhatsApp studio belum diisi di config/site.php.
     */
    public static function receiptLink(Payment $payment): ?string
    {
        $admin = preg_replace('/\D/', '', (string) config('site.contact.whatsapp'));

        if ($admin === '') {
            return null;
        }

        return self::chatUrl($admin, self::receiptMessage($payment));
    }

    /**
     * Langsung ke api.whatsapp.com, bukan lewat wa.me: pengalihan wa.me →
     * api.whatsapp.com merusak emoji 4-byte (😊, 🙏🏻) jadi "��" di WhatsApp
     * Web/Desktop. Tujuan akhirnya sama, hanya tanpa pengalihan itu.
     */
    private static function chatUrl(string $number, string $text): string
    {
        return 'https://api.whatsapp.com/send?phone='.$number.'&text='.rawurlencode($text);
    }

    /** Isi pesan bukti pembayaran, ditulis dari sudut pandang orang tua. */
    public static function receiptMessage(Payment $payment): string
    {
        $lines = array_filter([
            'No. Invoice: '.$payment->invoice_number,
            'Nama murid: '.($payment->student?->name ?? '-'),
            'Jumlah: Rp '.number_format((float) $payment->payment_amount, 0, ',', '.'),
            $payment->paid_at ? 'Dibayar pada: '.$payment->paid_at->format('d/m/Y H:i') : null,
            'Metode: '.$payment->methodLabel(),
        ]);

        return 'Halo Admin '.config('site.name').", saya sudah membayar invoice berikut.\n\n"
            .implode("\n", $lines)
            ."\n\nBukti pembayaran bisa dilihat di sini:\n".$payment->payUrl();
    }

    /**
     * Isi pesan invoice, mengikuti template sapaan dari admin studio. Nadanya
     * dibedakan: invoice yang belum dibayar berisi ajakan membayar, yang sudah
     * lunas jadi tanda terima.
     */
    public static function message(Payment $payment, ?string $payUrl = null): string
    {
        $student = $payment->student;
        $paid = $payment->payment_status === 'paid';

        $greeting = 'Hai wali murid '.($student?->name ?? 'anak').', apa kabar? Semoga selalu dalam keadaan yang sehat☺️';

        $intro = $paid
            ? 'Kami dari '.config('site.name').' ingin mengonfirmasi bahwa pembayaran biaya les sudah kami terima, dengan rincian:'
            : 'Kami dari '.config('site.name').' ingin menginformasikan untuk biaya les dengan rincian:';

        $lines = array_filter([
            'No. Invoice: '.$payment->invoice_number,
            'Nama murid: '.($student?->name ?? '-'),
            // Nama bulan saja ("Oktober"); tagihan lepas tanpa periode tidak punya baris ini.
            $payment->billing_period
                ? 'Periode pembayaran : '.Carbon::createFromFormat('Y-m-d', $payment->billing_period.'-01')->locale('id')->translatedFormat('F')
                : null,
            $payment->due_date ? 'Jatuh tempo: '.$payment->due_date->format('d/m/Y') : null,
            'Jumlah: Rp '.number_format((float) $payment->payment_amount, 0, ',', '.'),
            'Metode: '.$payment->methodLabel(),
            'Status: '.($paid ? 'LUNAS' : 'Belum dibayar'),
        ]);

        $closing = match (true) {
            $paid => null,
            $payUrl !== null => "Pembayaran bisa dilakukan lewat tautan berikut:\n".$payUrl,
            $payment->isOverdue() => 'Invoice ini sudah lewat jatuh tempo '.$payment->daysOverdue()
                .' hari. Mohon segera diselesaikan ya.',
            default => 'Mohon pembayaran diselesaikan sebelum tanggal jatuh tempo ya.',
        };

        // Dua baris kosong sebelum rincian memang bagian dari template.
        return $greeting."\n\n".$intro."\n\n\n".implode("\n", $lines)."\n\n"
            .($closing !== null ? $closing."\n\n" : '')
            .'Terima kasih banyak😊🙏🏻';
    }
}
