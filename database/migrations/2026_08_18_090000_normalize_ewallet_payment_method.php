<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * QRIS dan e-wallet sempat dipisah menjadi dua kategori, lalu digabung lagi.
     *
     * Pemisahannya tidak bisa ditegakkan: hanya GoPay & ShopeePay yang punya
     * channel sendiri di Midtrans, sedangkan DANA, OVO, dan LinkAja membayar
     * lewat QRIS dan dilaporkan sebagai 'qris' — kategori "E-Wallet" akan
     * selamanya kosong dari dompet yang paling sering dipakai.
     *
     * Invoice yang terlanjur tersimpan sebagai 'ewallet' dinormalkan ke 'qris'
     * supaya laporan keuangan tidak punya dua kategori untuk hal yang sama.
     * Channel aslinya tetap utuh di gateway_payment_type.
     */
    public function up(): void
    {
        DB::table('payments')->where('payment_method', 'ewallet')->update(['payment_method' => 'qris']);
    }

    public function down(): void
    {
        // Tidak bisa dibalik: setelah digabung, tidak ada lagi penanda invoice
        // mana yang dulunya 'ewallet'. gateway_payment_type-lah rujukannya.
    }
};
