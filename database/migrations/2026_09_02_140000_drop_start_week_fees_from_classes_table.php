<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga bulan pertama ternyata tidak diketik, melainkan dihitung.
     *
     * Aturan sanggarnya: murid membayar pekan yang benar-benar ia dapat. Masuk
     * pekan ke-2 berarti kebagian pekan 2, 3, 4 — tiga perempat bulan, tiga
     * perempat iuran. Tarif sepekan adalah class_fee dibagi empat.
     *
     * Karena itu daftar harga per pekan yang sempat disimpan di kolom
     * `start_week_fees` tidak lagi punya guna: angkanya seluruhnya bisa
     * diturunkan dari class_fee, dan kolom yang bisa menyimpang dari perhitungan
     * hanya menunggu saat untuk berbohong. Lihat ClassRoom::feeForStartWeek().
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('start_week_fees');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->json('start_week_fees')->nullable()->after('registration_fee');
        });
    }
};
