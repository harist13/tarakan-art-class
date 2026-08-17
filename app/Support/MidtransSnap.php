<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pembungkus tipis API Midtrans Snap (tanpa SDK — cukup HTTP client Laravel).
 *
 * Alurnya: admin mengirim tautan /bayar/{pay_token} lewat WhatsApp, orang tua
 * membukanya, DI SAAT ITULAH transaksi Snap dibuat. Token Snap tidak dibuat
 * lebih awal karena masa berlakunya terbatas — tautan yang dibuka tiga hari
 * setelah dikirim tetap harus bisa dipakai.
 */
class MidtransSnap
{
    /** Belum dikonfigurasi = fitur mati total, aplikasi kembali ke konfirmasi manual. */
    public function isConfigured(): bool
    {
        return ! empty(config('midtrans.server_key'));
    }

    public function clientKey(): ?string
    {
        return config('midtrans.client_key');
    }

    public function isProduction(): bool
    {
        return (bool) config('midtrans.is_production');
    }

    public function snapJsUrl(): string
    {
        return $this->endpoint('snap_js');
    }

    /**
     * Apakah alamat notifikasi kita dapat dijangkau server Midtrans?
     *
     * Notifikasi dikirim dari internet, jadi APP_URL harus menunjuk ke alamat
     * publik. Alamat pengembangan lokal gagal diam-diam: Midtrans tidak pernah
     * berhasil menyambung, sementara di layar admin pembayaran terlihat sukses
     * tapi invoicenya tak kunjung lunas — gejala yang sulit ditebak asalnya.
     */
    public function webhookReachable(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        // Akhiran yang tidak pernah ada di DNS publik: .test & .localhost
        // dicadangkan RFC 6761 (dipakai Laragon/Valet), .local milik mDNS.
        foreach (['.test', '.localhost', '.local', '.invalid', '.example', '.internal', '.home', '.lan'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return false;
            }
        }

        // Alamat IP privat/khusus hanya berlaku di dalam jaringan sendiri.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        return true;
    }

    /**
     * Transaksi Snap yang siap dibayar untuk sebuah invoice.
     *
     * Token lama dipakai ulang selama belum kedaluwarsa DAN nominalnya belum
     * berubah — order_id yang sama tidak boleh dikirim dua kali ke Midtrans.
     * Begitu invoice direvisi, transaksi baru dibuat dengan order_id berikutnya.
     *
     * @return array{token: string, redirect_url: string}
     */
    public function transactionFor(Payment $payment): array
    {
        if ($this->reusableToken($payment)) {
            return [
                'token' => $payment->snap_token,
                'redirect_url' => $payment->snap_redirect_url,
            ];
        }

        $orderId = $this->nextOrderId($payment);
        $result = $this->createTransaction($payment, $orderId);

        $payment->forceFill([
            'snap_order_id' => $orderId,
            'snap_token' => $result['token'],
            'snap_redirect_url' => $result['redirect_url'],
            'snap_expires_at' => now()->addHours((int) config('midtrans.expiry_hours', 24)),
            'gateway_status' => 'pending',
        ])->save();

        // Jejak untuk menelusuri "popup bilang sukses tapi invoice tetap Unpaid":
        // order_id di sini harus sama dengan yang muncul di Dashboard Midtrans.
        Log::info("Snap dibuat untuk {$payment->invoice_number}: order_id={$orderId}, nominal=".
            (int) round((float) $payment->payment_amount));

        return $result;
    }

    /**
     * Token masih bisa dipakai ulang? Diberi jeda 5 menit sebelum kedaluwarsa
     * supaya orang tua tidak kebagian token yang mati di tengah pembayaran.
     * Revisi nominal invoice membuang token lama lewat hook di model Payment.
     */
    private function reusableToken(Payment $payment): bool
    {
        return $payment->snap_token !== null
            && $payment->snap_expires_at !== null
            && $payment->snap_expires_at->isAfter(now()->addMinutes(5));
    }

    /**
     * order_id untuk Midtrans: INV001-17-1, INV001-17-2, …
     *
     * Susunannya {nomor invoice}-{id baris}-{percobaan}. Angka tengah wajib ada
     * karena nomor invoice BISA terpakai ulang: Payment::generateInvoiceNumber()
     * menghitung dari baris terakhir, jadi menghapus invoice terbaru membuat
     * invoice berikutnya memakai nomor yang sama. Midtrans menuntut order_id
     * unik selamanya — tanpa id baris, invoice pengganti langsung ditolak
     * "transaction_details.order_id sudah digunakan" dan orang tua melihat
     * halaman error. Id baris tidak pernah dipakai ulang (auto-increment).
     */
    private function nextOrderId(Payment $payment): string
    {
        $attempt = 1;

        if ($payment->snap_order_id && preg_match('/-(\d+)$/', $payment->snap_order_id, $m)) {
            $attempt = (int) $m[1] + 1;
        } elseif ($payment->snap_order_id) {
            $attempt = 2;
        }

        return $payment->invoice_number.'-'.$payment->getKey().'-'.$attempt;
    }

    /**
     * @return array{token: string, redirect_url: string}
     */
    private function createTransaction(Payment $payment, string $orderId): array
    {
        $student = $payment->student;
        // Midtrans menolak gross_amount berdesimal; nominal dibulatkan ke rupiah
        // penuh dan item_details harus berjumlah sama persis dengan gross_amount.
        $amount = (int) round((float) $payment->payment_amount);

        $response = $this->request()->post($this->endpoint('snap'), [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'item_details' => [[
                'id' => $payment->invoice_number,
                'price' => $amount,
                'quantity' => 1,
                // Nama item dibatasi 50 karakter oleh Midtrans.
                'name' => mb_substr('Kelas seni - '.($student?->name ?? 'Murid'), 0, 50),
            ]],
            'customer_details' => array_filter([
                'first_name' => mb_substr($student?->parent_name ?: ($student?->name ?? 'Wali Murid'), 0, 50),
                'phone' => $student?->whatsappNumber(),
            ]),
            'expiry' => [
                'unit' => 'hour',
                'duration' => (int) config('midtrans.expiry_hours', 24),
            ],
            'callbacks' => [
                'finish' => route('pay.show', $payment->payToken()),
            ],
        ]);

        if ($response->failed()) {
            // error_messages berisi alasan yang bisa ditindaklanjuti admin
            // (mis. "gross_amount is not equal to the sum of item_details").
            $reason = implode(' ', (array) $response->json('error_messages', [$response->status()]));

            throw new RuntimeException('Midtrans menolak permintaan pembayaran: '.$reason);
        }

        return [
            'token' => $response->json('token'),
            'redirect_url' => $response->json('redirect_url'),
        ];
    }

    /** Status terkini dari Core API — dipakai bila webhook tidak sampai. */
    public function status(string $reference): array
    {
        $result = $this->request()->get($this->endpoint('api')."/{$reference}/status")->json() ?? [];

        Log::info("Status Midtrans untuk {$reference}: ".
            ($result['transaction_status'] ?? ($result['status_message'] ?? '(kosong)')).
            ' / '.($result['payment_type'] ?? '-'));

        return $result;
    }

    /**
     * Kunci terbaik untuk menanyakan status sebuah invoice.
     *
     * transaction_id didahulukan bila sudah pernah diketahui: pencarian lewat
     * order_id tidak selalu menemukan transaksi e-wallet, sedangkan
     * transaction_id selalu menunjuk ke satu transaksi yang pasti.
     */
    public function statusFor(Payment $payment): array
    {
        return $this->status($payment->gateway_transaction_id ?: $payment->snap_order_id);
    }

    /**
     * Terapkan status dari Midtrans ke invoice. Dipakai webhook maupun tombol
     * "cek status" manual, supaya keduanya tidak pernah berbeda perlakuan.
     *
     * Mengembalikan true bila invoice ini BARU saja menjadi lunas.
     */
    public function applyStatus(Payment $payment, array $payload): bool
    {
        if ($payment->payment_status === 'paid') {
            return false;
        }

        if ($this->isSettled($payload)) {
            // PaymentObserver yang mencatat pemasukannya ke Laporan Keuangan
            // dan mencabut penangguhan murid begitu status jadi "paid".
            $payment->forceFill([
                'payment_status' => 'paid',
                'payment_method' => $this->methodFor($payload['payment_type'] ?? null),
                'gateway_status' => $payload['transaction_status'],
                'gateway_payment_type' => $payload['payment_type'] ?? null,
                'gateway_transaction_id' => $payload['transaction_id'] ?? $payment->gateway_transaction_id,
                'paid_at' => now(),
            ])->save();

            return true;
        }

        // Gagal / kedaluwarsa: token dibuang agar tautan yang sama membuat
        // transaksi baru saat dibuka lagi, bukan menampilkan token mati.
        $failed = $this->isFailed($payload);

        $payment->forceFill([
            'gateway_status' => $payload['transaction_status'] ?? 'unknown',
            'gateway_payment_type' => $payload['payment_type'] ?? $payment->gateway_payment_type,
            'gateway_transaction_id' => $payload['transaction_id'] ?? $payment->gateway_transaction_id,
            'snap_token' => $failed ? null : $payment->snap_token,
            'snap_expires_at' => $failed ? null : $payment->snap_expires_at,
        ])->save();

        return false;
    }

    /**
     * Notifikasi Midtrans hanya boleh dipercaya bila signature-nya cocok.
     * Rumus resmi: sha512(order_id + status_code + gross_amount + server_key).
     */
    public function signatureIsValid(array $payload): bool
    {
        $expected = hash('sha512',
            ($payload['order_id'] ?? '')
            .($payload['status_code'] ?? '')
            .($payload['gross_amount'] ?? '')
            .config('midtrans.server_key')
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /**
     * Notifikasi yang berarti "uang sudah masuk".
     *
     * `capture` (kartu kredit) hanya dihitung lunas bila fraud_status-nya
     * accept — challenge masih menunggu keputusan manual di dashboard.
     */
    public function isSettled(array $payload): bool
    {
        $status = $payload['transaction_status'] ?? null;

        return $status === 'settlement'
            || ($status === 'capture' && ($payload['fraud_status'] ?? 'accept') === 'accept');
    }

    /** Notifikasi yang berarti transaksinya batal — tautan boleh dibuat ulang. */
    public function isFailed(array $payload): bool
    {
        return in_array($payload['transaction_status'] ?? null, ['deny', 'cancel', 'expire', 'failure'], true);
    }

    /**
     * Channel Midtrans dipetakan ke empat kosakata payments.payment_method
     * supaya Laporan Keuangan tidak pecah jadi puluhan kategori tiap kali
     * Midtrans menambah channel baru. Nama channel aslinya tetap utuh di
     * kolom gateway_payment_type, jadi tidak ada informasi yang hilang.
     *
     * Channel yang belum dikenal masuk "transfer" — kategori paling netral
     * untuk uang yang masuk lewat gateway.
     */
    public function methodFor(?string $paymentType): string
    {
        return match ($paymentType) {
            // E-wallet & QR. DANA, LinkAja, OVO, dan dompet lain membayar lewat
            // QRIS (standar nasional), jadi Midtrans melaporkannya sebagai qris.
            'qris', 'other_qris', 'gopay', 'shopeepay', 'dana' => 'qris',

            // Virtual Account seluruh bank + Mandiri Bill Payment (echannel).
            'bank_transfer', 'echannel', 'permata', 'bca_va', 'bni_va',
            'bri_va', 'cimb_va', 'mandiri_va', 'other_va' => 'virtual_account',

            // Indomaret / Alfamart — orang tua menyetor tunai di gerai.
            'cstore' => 'cash',

            default => 'transfer',
        };
    }

    private function request(): PendingRequest
    {
        // Midtrans memakai Basic Auth: server key sebagai username, password kosong.
        return Http::withBasicAuth((string) config('midtrans.server_key'), '')
            ->acceptJson()
            ->timeout(20);
    }

    private function endpoint(string $key): string
    {
        $env = $this->isProduction() ? 'production' : 'sandbox';

        return config("midtrans.endpoints.{$env}.{$key}");
    }
}
