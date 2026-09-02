<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiga hal yang sebelumnya tidak tercatat di kelas, padahal ikut menentukan harga:
     *
     *   class_type       — trial | regular. Menggantikan saklar "Pengulangan" di form:
     *                      trial = kelas sekali pertemuan, regular = mingguan. Nilainya
     *                      tetap dicerminkan ke `is_recurring`, yang masih jadi acuan
     *                      seluruh perhitungan sesi di ClassRoom.
     *   start_week       — murid mulai pada pekan ke berapa (1–5). Harga berbeda untuk
     *                      yang masuk di awal, tengah, atau akhir siklus kelas.
     *   registration_fee — uang pendaftaran, diisi manual per kelas. Terpisah dari
     *                      class_fee agar biaya sekali bayar tidak tercampur ke iuran.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('class_type', 20)->default('regular')->after('is_recurring');
            $table->unsignedTinyInteger('start_week')->default(1)->after('class_type');
            $table->decimal('registration_fee', 12, 2)->default(0)->after('class_fee');
        });

        // Kelas sekali jalan yang sudah ada persis sama artinya dengan trial pada
        // pemodelan baru — tanpa backfill ini, badge tipenya akan berbohong.
        DB::table('classes')->where('is_recurring', false)->update(['class_type' => 'trial']);
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['class_type', 'start_week', 'registration_fee']);
        });
    }
};
