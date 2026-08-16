<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal sesi yang ditinggalkan murid.
 *
 * Sebelumnya request hanya menyimpan kelas asalnya, sehingga "sesi mana yang
 * dilewatkan" harus ditebak dari kejadian terdekat kelas itu — tebakan yang
 * bergeser seiring waktu berjalan. Absensi butuh jawaban yang pasti: murid
 * yang sesinya pindah tidak boleh ikut tercatat di sesi yang ditinggalkannya.
 *
 * Nullable karena request lama tidak punya nilainya. Baris tanpa missed_date
 * diperlakukan sebagai "tidak diketahui" — absensi tidak menyembunyikan
 * muridnya, dan itu memang perilaku yang aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->date('missed_date')->nullable()->after('origin_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->dropColumn('missed_date');
        });
    }
};
