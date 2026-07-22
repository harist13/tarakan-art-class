<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambahkan channel gateway QRIS & Virtual Account (Midtrans/Xendit belum aktif,
     * dicatat manual oleh admin).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash', 'transfer', 'qris', 'virtual_account') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash', 'transfer') NOT NULL");
    }
};
