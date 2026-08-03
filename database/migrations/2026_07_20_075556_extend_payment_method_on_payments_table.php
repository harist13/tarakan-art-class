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
        // ENUM MODIFY hanya didukung MySQL/MariaDB. SQLite (dipakai saat testing)
        // menyimpan kolom ini sebagai teks bebas, jadi tidak perlu diubah.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash', 'transfer', 'qris', 'virtual_account') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cash', 'transfer') NOT NULL");
        }
    }
};
