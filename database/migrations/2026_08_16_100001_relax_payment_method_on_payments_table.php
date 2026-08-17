<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * payments.payment_method dilepas dari ENUM menjadi string biasa.
     *
     * Dua alasan. Pertama, daftar channel yang dilaporkan Midtrans akan terus
     * bertambah dan tidak layak dikejar dengan ALTER TABLE tiap kali. Kedua,
     * migrasi ENUM sebelumnya hanya berjalan di MySQL — di SQLite (dipakai saat
     * testing) CHECK constraint-nya masih membatasi ke cash/transfer, sehingga
     * pembayaran QRIS/VA lolos di produksi tapi gagal di test.
     *
     * Nilai yang sah tetap dijaga Rule::in() di PaymentController dan pemetaan
     * MidtransSnap::methodFor().
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method', 30)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash', 'transfer', 'qris', 'virtual_account') NOT NULL");
        }
    }
};
