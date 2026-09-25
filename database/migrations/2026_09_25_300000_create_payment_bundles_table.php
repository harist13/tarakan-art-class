<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tagihan gabungan: beberapa invoice (mis. kakak-adik) dibayar sekali
     * lewat satu tautan dan satu transaksi Midtrans.
     *
     * Invoice-nya sendiri tidak berubah — tetap satu baris per murid per bulan,
     * dengan tautan bayarnya sendiri. Gabungan hanya pembungkus pembayaran;
     * begitu lunas, tiap invoice di dalamnya ditandai lunas satu per satu
     * sehingga pencatatan keuangan per invoice (PaymentObserver) tetap jalan.
     *
     * Kolom Snap-nya sama dengan di payments, ditambah snap_amount: total yang
     * dipakai saat transaksi dibuat. Total gabungan bisa berubah (salah satu
     * invoice dibayar terpisah atau direvisi), dan token lama hanya boleh
     * dipakai ulang selama totalnya masih sama.
     */
    public function up(): void
    {
        Schema::create('payment_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('pay_token', 64)->unique();
            $table->string('snap_order_id')->nullable()->unique();
            $table->string('snap_token')->nullable();
            $table->string('snap_redirect_url')->nullable();
            $table->timestamp('snap_expires_at')->nullable();
            $table->unsignedBigInteger('snap_amount')->nullable();
            $table->string('gateway_status')->nullable();
            $table->string('gateway_payment_type')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            // Dibatalkan admin, BUKAN dihapus: orang tua mungkin sudah memegang
            // nomor VA dari gabungan ini. Kalau VA itu tetap dibayar, webhook
            // harus masih menemukan gabungannya untuk melunasi invoicenya.
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_bundle_id')->constrained('payment_bundles')->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->unique(['payment_bundle_id', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_bundle_items');
        Schema::dropIfExists('payment_bundles');
    }
};
