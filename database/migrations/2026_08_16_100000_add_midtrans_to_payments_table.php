<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Integrasi Midtrans Snap pada Payment Management (F6).
     *
     * `pay_token` adalah kunci tautan publik /bayar/{token} yang dikirim lewat
     * WhatsApp — sengaja acak, bukan invoice_number, supaya nomor invoice tidak
     * bisa ditebak berurutan untuk mengintip data murid & nominal tagihan.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('pay_token', 40)->nullable()->unique()->after('notes');

            // Satu invoice bisa beberapa kali dibuatkan transaksi Snap (token
            // kedaluwarsa, nominal direvisi), jadi order_id disimpan terpisah.
            $table->string('snap_order_id')->nullable()->unique()->after('pay_token');
            $table->string('snap_token')->nullable()->after('snap_order_id');
            $table->string('snap_redirect_url')->nullable()->after('snap_token');
            $table->timestamp('snap_expires_at')->nullable()->after('snap_redirect_url');

            // Jejak dari gateway — dipakai untuk audit & menampilkan status di
            // daftar pembayaran tanpa harus memanggil API Midtrans lagi.
            $table->string('gateway_status')->nullable()->after('snap_expires_at');
            $table->string('gateway_payment_type')->nullable()->after('gateway_status');
            $table->timestamp('paid_at')->nullable()->after('gateway_payment_type');
        });

        // Invoice yang sudah ada ikut dapat token, supaya tautan pembayarannya
        // bisa langsung dikirim tanpa menunggu invoice baru dibuat.
        DB::table('payments')->whereNull('pay_token')->orderBy('id')->each(function ($row) {
            DB::table('payments')->where('id', $row->id)->update(['pay_token' => Str::random(32)]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'pay_token',
                'snap_order_id',
                'snap_token',
                'snap_redirect_url',
                'snap_expires_at',
                'gateway_status',
                'gateway_payment_type',
                'paid_at',
            ]);
        });
    }
};
