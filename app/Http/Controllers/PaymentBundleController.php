<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentBundle;
use App\Support\GuardianGroups;
use App\Support\InvoiceWhatsApp;
use App\Support\MidtransSnap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tagihan gabungan — beberapa invoice satu keluarga (mis. kakak-adik) dibayar
 * sekali lewat satu tautan. Admin memilih sendiri invoice mana yang digabung;
 * sistem hanya menyodorkan kelompok keluarganya (lihat GuardianGroups).
 */
class PaymentBundleController extends Controller
{
    public function index(MidtransSnap $snap)
    {
        $groups = GuardianGroups::unpaid();

        // Gabungan yang masih berjalan di atas, lalu yang lunas / dibatalkan.
        $bundles = PaymentBundle::with(['payments.student', 'creator'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->sortBy(fn (PaymentBundle $b) => $b->isOpen() ? 0 : 1)
            ->values();

        return view('payment-bundles.index', [
            'groups' => $groups,
            'bundles' => $bundles,
            'midtransActive' => $snap->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'payment_ids' => ['required', 'array', 'min:2'],
            'payment_ids.*' => ['integer', 'distinct', 'exists:payments,id'],
        ], [
            'payment_ids.required' => 'Pilih minimal dua invoice untuk digabung.',
            'payment_ids.min' => 'Pilih minimal dua invoice untuk digabung.',
        ]);

        // Kelompoknya dihitung ulang di server, bukan dipercaya dari form:
        // invoice bisa saja sudah lunas atau berpindah keluarga sejak halaman
        // dibuka, dan satu tautan tidak boleh menagih dua keluarga berbeda.
        $keys = GuardianGroups::keyByPayment();
        $groupKeys = collect($data['payment_ids'])->map(fn ($id) => $keys[$id] ?? null);

        if ($groupKeys->contains(null)) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Ada invoice yang sudah lunas atau tidak lagi bisa digabung. Muat ulang halaman lalu coba lagi.',
            ]);
        }
        if ($groupKeys->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'payment_ids' => 'Invoice yang digabung harus dari satu keluarga (nomor WhatsApp atau nama wali yang sama).',
            ]);
        }

        $bundle = DB::transaction(function () use ($data) {
            // Satu invoice hanya boleh ada di satu gabungan yang berjalan —
            // kalau tidak, orang tua bisa membayarnya dua kali lewat dua tautan.
            // Gabungan lama dibatalkan utuh (bukan dikurangi isinya): kalau VA
            // lamanya tetap dibayar, invoicenya masih bisa dilunasi webhook.
            $lama = PaymentBundle::whereNull('paid_at')->whereNull('cancelled_at')
                ->whereHas('payments', fn ($q) => $q->whereIn('payments.id', $data['payment_ids']))
                ->get();

            foreach ($lama as $old) {
                $old->forceFill(['cancelled_at' => now()])->save();
                ActivityLog::record('updated', $old, "Tagihan gabungan {$old->code} dibatalkan (invoicenya digabung ulang)");
            }

            $bundle = PaymentBundle::create(['created_by' => auth()->id()]);
            $bundle->payments()->attach($data['payment_ids']);
            $bundle->load('payments.student');

            ActivityLog::record('created', $bundle, "Membuat tagihan gabungan {$bundle->code} untuk {$bundle->studentNames()}: ".
                $bundle->payments->pluck('invoice_number')->implode(', '));

            return $bundle;
        });

        return redirect()->route('payment-bundles.index')
            ->with('success', "Tagihan gabungan {$bundle->code} untuk {$bundle->studentNames()} berhasil dibuat. Kirim lewat tombol WhatsApp di bawah.");
    }

    public function sendWhatsapp(PaymentBundle $bundle, MidtransSnap $snap)
    {
        $bundle->load('payments.student');

        if (! $bundle->isOpen()) {
            return back()->with('error', "Tagihan gabungan {$bundle->code} sudah lunas atau dibatalkan.");
        }

        $link = InvoiceWhatsApp::bundleLink($bundle, $snap->isConfigured() ? $bundle->payUrl() : null);

        if ($link === null) {
            return back()->with('error', 'Nomor HP wali murid belum terisi atau tidak valid. Lengkapi dulu di menu Murid.');
        }

        ActivityLog::record('sent', $bundle, "Mengirim tagihan gabungan {$bundle->code} via WhatsApp ke wali {$bundle->studentNames()}");

        return redirect()->away($link);
    }

    /** Jaring pengaman bila notifikasi Midtrans tidak sampai — pasangan PaymentController::syncGateway. */
    public function syncGateway(PaymentBundle $bundle, MidtransSnap $snap)
    {
        $bundle->load('payments.student');

        if (! $snap->isConfigured() || ! $bundle->snap_order_id) {
            return back()->with('error', "Tagihan gabungan {$bundle->code} belum punya transaksi Midtrans untuk dicek.");
        }

        $status = $snap->bundleStatusFor($bundle);

        if (empty($status['transaction_status'])) {
            return back()->with('error', "Midtrans belum mencatat pembayaran untuk order {$bundle->snap_order_id} — \"".
                ($status['status_message'] ?? 'Midtrans tidak menjawab.').'"');
        }

        $lunas = DB::transaction(fn () => $snap->applyBundleStatus($bundle, $status));

        if ($lunas) {
            ActivityLog::record('updated', $bundle, "Tagihan gabungan {$bundle->code} lunas via Midtrans (cek manual)");

            return back()->with('success', "Tagihan gabungan {$bundle->code} LUNAS: ".
                collect($lunas)->pluck('invoice_number')->implode(', ').' tercatat di keuangan.');
        }

        if ($bundle->fresh('payments')->isPaid()) {
            return back()->with('success', "Tagihan gabungan {$bundle->code} memang sudah LUNAS.");
        }

        return back()->with('error', "Tagihan gabungan {$bundle->code} belum lunas (status Midtrans: {$status['transaction_status']}).");
    }

    /**
     * Batalkan gabungan. Invoice di dalamnya tidak tersentuh — tetap bisa
     * dibayar lewat tautannya masing-masing.
     *
     * Ditandai, bukan dihapus: bila orang tua sudah memegang nomor VA dari
     * tautan ini lalu tetap membayarnya, webhook masih bisa menemukan
     * gabungannya dan melunasi invoicenya.
     */
    public function destroy(PaymentBundle $bundle)
    {
        $bundle->load('payments');

        if (! $bundle->isOpen()) {
            return back()->with('error', "Tagihan gabungan {$bundle->code} sudah lunas atau sudah dibatalkan.");
        }

        DB::transaction(function () use ($bundle) {
            $bundle->forceFill(['cancelled_at' => now()])->save();
            ActivityLog::record('updated', $bundle, "Membatalkan tagihan gabungan {$bundle->code}");
        });

        return back()->with('success', "Tagihan gabungan {$bundle->code} dibatalkan. Invoice di dalamnya tetap bisa dibayar satu per satu.");
    }
}
