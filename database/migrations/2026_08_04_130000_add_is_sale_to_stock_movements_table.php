<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasangan dari is_purchase: menandai stok keluar yang benar-benar terjual,
     * supaya bisa dibedakan dari barang rusak/hilang atau yang dipakai sendiri
     * untuk kegiatan kelas. Hanya yang bertanda terjual yang dicatat sebagai
     * pemasukan di Laporan Keuangan (F7).
     */
    public function up(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->boolean('is_sale')->default(false)->after('is_purchase');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropColumn('is_sale');
        });
    }
};
