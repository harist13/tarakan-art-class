<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jam selesai kelas.
     *
     * Selama ini slot hanya menyimpan jam mulai, sehingga kalender tidak bisa
     * menggambarkan sesi sebagai rentang — semua kelas tampak sebagai satu titik
     * pada jam yang sama panjangnya, dan admin tidak bisa melihat dua kelas yang
     * saling bertumpuk.
     *
     * Nullable: slot yang sudah ada belum punya jam selesai, dan menebaknya
     * (mis. "satu jam setelah mulai") akan tampil sebagai fakta di kalender
     * padahal tidak pernah ada yang menetapkannya. Form kelas meminta jam ini
     * untuk setiap penyimpanan berikutnya, jadi data lama terisi sendiri
     * begitu kelasnya disunting.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->time('schedule_end_time')->nullable()->after('schedule_time');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('schedule_end_time');
        });
    }
};
