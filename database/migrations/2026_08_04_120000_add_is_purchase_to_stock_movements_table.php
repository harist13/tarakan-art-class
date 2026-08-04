<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai stok masuk yang berasal dari pembelian, supaya bisa dibedakan
     * dari retur/koreksi stok yang tidak mengeluarkan uang. Hanya yang bertanda
     * pembelian yang dicatat sebagai pengeluaran di Laporan Keuangan (F7).
     */
    public function up(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->boolean('is_purchase')->default(false)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropColumn('is_purchase');
        });
    }
};
