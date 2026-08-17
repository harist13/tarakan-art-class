<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * transaction_id milik Midtrans.
     *
     * Pelacakan status lewat order_id tidak selalu berhasil untuk channel
     * e-wallet; transaction_id adalah kunci yang paling dapat diandalkan.
     * Nilainya baru diketahui dari notifikasi/status pertama, jadi disimpan
     * begitu terlihat dan dipakai untuk pengecekan berikutnya.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_transaction_id')->nullable()->after('gateway_payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway_transaction_id');
        });
    }
};
