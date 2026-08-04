<?php

use App\Models\Payment;
use App\Models\Transaction;
use App\Observers\PaymentObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjamin invarian "1 payment paid = 1 transaksi pemasukan":
     * 1. buang transaksi otomatis yang dobel (sisakan yang terlama),
     * 2. kunci dengan unique index supaya tidak bisa dobel lagi,
     * 3. backfill invoice paid lama yang belum pernah masuk Laporan Keuangan.
     */
    public function up(): void
    {
        $this->removeDuplicates();

        Schema::table('transactions', function (Blueprint $table) {
            $table->unique('payment_id', 'transactions_payment_id_unique');
        });

        $this->backfillPaidPayments();
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_payment_id_unique');
        });
    }

    private function removeDuplicates(): void
    {
        $duplicates = Transaction::query()
            ->whereNotNull('payment_id')
            ->selectRaw('payment_id, MIN(id) as keep_id')
            ->groupBy('payment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            Transaction::where('payment_id', $row->payment_id)
                ->where('id', '!=', $row->keep_id)
                ->delete();
        }
    }

    private function backfillPaidPayments(): void
    {
        Payment::query()
            ->with('student')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('transaction')
            ->chunkById(200, function ($payments) {
                foreach ($payments as $payment) {
                    Transaction::create([
                        'type' => 'income',
                        'category' => PaymentObserver::CATEGORY,
                        'amount' => $payment->payment_amount,
                        'transaction_date' => $payment->payment_date,
                        'description' => PaymentObserver::describe($payment),
                        'payment_id' => $payment->id,
                        'recorded_by' => null,
                    ]);
                }
            });
    }
};
