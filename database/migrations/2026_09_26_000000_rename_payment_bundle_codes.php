<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kode tagihan gabungan "GAB-001" → "Gabungan-001", supaya terbaca jelas
     * di daftar pembayaran tanpa perlu tahu singkatannya.
     *
     * snap_order_id sengaja tidak diubah: order yang sudah dikirim ke Midtrans
     * harus tetap cocok saat notifikasinya tiba.
     */
    public function up(): void
    {
        DB::table('payment_bundles')->where('code', 'like', 'GAB-%')->orderBy('id')->get(['id', 'code'])
            ->each(fn ($b) => DB::table('payment_bundles')->where('id', $b->id)
                ->update(['code' => 'Gabungan-'.substr($b->code, 4)]));
    }

    public function down(): void
    {
        DB::table('payment_bundles')->where('code', 'like', 'Gabungan-%')->orderBy('id')->get(['id', 'code'])
            ->each(fn ($b) => DB::table('payment_bundles')->where('id', $b->id)
                ->update(['code' => 'GAB-'.substr($b->code, 9)]));
    }
};
