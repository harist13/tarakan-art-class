<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Metode QRIS / E-Wallet dihapus dari pilihan invoice.
     *
     * Popup Midtrans sudah lama tidak menawarkannya — transaksi QRIS & dompet
     * digital tidak bisa dicek ulang lewat order_id (lihat config/midtrans.php) —
     * sehingga pilihan itu di form hanya menjanjikan cara bayar yang tidak ada.
     *
     * Invoice yang terlanjur tercatat 'qris' (atau 'ewallet', nilai lama sebelum
     * digabung ke 'qris') dipindahkan ke 'transfer', kategori yang juga dipakai
     * MidtransSnap::methodFor() untuk channel yang tidak punya kategori sendiri.
     * Channel aslinya tetap utuh di gateway_payment_type.
     */
    public function up(): void
    {
        DB::table('payments')->whereIn('payment_method', ['qris', 'ewallet'])->update(['payment_method' => 'transfer']);
    }

    public function down(): void
    {
        // Tidak bisa dibalik: setelah digabung, tidak ada lagi penanda invoice mana
        // yang dulunya QRIS. gateway_payment_type-lah rujukannya.
    }
};
