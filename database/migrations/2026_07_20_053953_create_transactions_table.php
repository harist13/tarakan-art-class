<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Financial Tracking (F7) + Profit/Loss Report
     * payment_id diisi otomatis jika transaksi income berasal dari Payment murid,
     * supaya Financial Tracking & Payment Management nyambung tanpa duplikasi input.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            $table->string('category'); // mis: SPP, Gaji, Perlengkapan, dll
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->text('description')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};