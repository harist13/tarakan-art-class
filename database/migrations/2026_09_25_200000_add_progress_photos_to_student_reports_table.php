<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto progres di raport: minggu pertama dan minggu terakhir periode.
     *
     * Terpisah dari Galeri Karya. Galeri adalah kumpulan karya yang SELESAI;
     * foto progres justru menunjukkan karya yang masih berjalan — mis. minggu
     * pertama baru blending kepala, minggu terakhir blending badan — supaya
     * orang tua paham kenapa satu gambar bisa makan waktu sebulan.
     *
     * Dua slot tetap, bukan tabel foto: yang diminta memang perbandingan awal
     * vs akhir, dan dua kolom membuat raport tetap satu baris utuh.
     */
    public function up(): void
    {
        Schema::table('student_reports', function (Blueprint $table) {
            $table->string('progress_first_photo')->nullable()->after('tutor_notes');
            $table->string('progress_first_caption')->nullable()->after('progress_first_photo');
            $table->string('progress_last_photo')->nullable()->after('progress_first_caption');
            $table->string('progress_last_caption')->nullable()->after('progress_last_photo');
        });
    }

    public function down(): void
    {
        Schema::table('student_reports', function (Blueprint $table) {
            $table->dropColumn([
                'progress_first_photo', 'progress_first_caption',
                'progress_last_photo', 'progress_last_caption',
            ]);
        });
    }
};
