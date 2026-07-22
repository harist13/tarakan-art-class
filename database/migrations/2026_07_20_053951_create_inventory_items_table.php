<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul: Inventory Management (F10)
     * remaining_stock dihitung otomatis (accessor/observer) dari SUM stock_in - SUM stock_out
     * pada tabel inventory_stock_movements, tapi disimpan juga sebagai kolom cache untuk performa.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code')->unique(); // auto-generate: INVT001
            $table->string('item_name');
            $table->decimal('purchase_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->integer('remaining_stock')->default(0); // cache, di-update via observer/event
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};