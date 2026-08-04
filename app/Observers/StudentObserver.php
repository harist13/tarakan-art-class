<?php

namespace App\Observers;

use App\Models\Student;

/**
 * Menghapus murid akan meng-cascade payment-nya di level database, sehingga
 * PaymentObserver tidak pernah terpanggil dan baris pemasukannya tertinggal
 * tanpa keterangan.
 *
 * Uang yang sudah masuk tetap dipertahankan di Laporan Keuangan supaya total
 * bulan berjalan & bulan lalu tidak berubah, tapi barisnya dilepas dari invoice
 * dan diberi keterangan agar bisa ditelusuri (serta bisa dihapus manual bila perlu).
 */
class StudentObserver
{
    public const DETACHED_NOTE = '(murid dihapus)';

    public function deleting(Student $student): void
    {
        $payments = $student->payments()->with('transaction')->get();

        foreach ($payments as $payment) {
            $transaction = $payment->transaction;

            if ($transaction) {
                $transaction->update([
                    'payment_id' => null,
                    'description' => trim("{$transaction->description} ".self::DETACHED_NOTE),
                ]);
            }

            // Dihapus lewat Eloquent (bukan cascade DB) agar event modelnya konsisten;
            // transaksinya sudah dilepas duluan sehingga tidak ikut terhapus.
            $payment->delete();
        }
    }
}
