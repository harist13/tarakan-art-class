<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\Transaction;

/**
 * Menjaga Payment Management (F6) selalu sinkron dengan Financial Tracking (F7).
 *
 * Aturan: setiap Payment berstatus "paid" WAJIB punya tepat satu transaksi
 * pemasukan di Laporan Keuangan. Begitu status kembali ke "unpaid" atau
 * invoice dihapus/void, transaksi otomatis tersebut ikut dibuang.
 */
class PaymentObserver
{
    public const CATEGORY = 'SPP / Pembayaran Kelas';

    /**
     * Dipanggil setelah create maupun update, sehingga pencatatan pemasukan
     * tetap jalan walau status diubah dari luar controller (seeder, tinker, dsb).
     */
    public function saved(Payment $payment): void
    {
        if ($payment->payment_status === 'paid') {
            $this->syncIncome($payment);

            return;
        }

        $payment->transaction()->delete();
    }

    /**
     * Pakai "deleting", bukan "deleted": FK transactions.payment_id memakai
     * nullOnDelete, jadi setelah payment terhapus barisnya sudah tidak terhubung.
     */
    public function deleting(Payment $payment): void
    {
        $payment->transaction()->delete();
    }

    /**
     * updateOrCreate memakai payment_id sebagai kunci agar tidak pernah dobel,
     * sekaligus ikut memperbarui nominal/tanggal bila invoice direvisi.
     */
    private function syncIncome(Payment $payment): void
    {
        $existing = $payment->transaction()->first();

        Transaction::updateOrCreate(
            ['payment_id' => $payment->id],
            [
                'type' => 'income',
                'category' => self::CATEGORY,
                'amount' => $payment->payment_amount,
                'transaction_date' => $payment->payment_date,
                'description' => self::describe($payment),
                // Pencatat pertama dipertahankan; auth()->id() bisa null di CLI.
                'recorded_by' => $existing?->recorded_by ?? auth()->id(),
            ]
        );
    }

    public static function describe(Payment $payment): string
    {
        $student = $payment->student?->name;

        return "Pembayaran {$payment->invoice_number}".($student ? " - {$student}" : '');
    }
}
