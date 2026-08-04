<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghubungkan pengeluaran otomatis di Laporan Keuangan (F7) dengan barang
     * yang dibeli di Inventory Management (F10), sejalan dengan payment_id untuk
     * pemasukan dari invoice.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('payment_id')
                ->constrained('inventory_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
        });
    }
};
