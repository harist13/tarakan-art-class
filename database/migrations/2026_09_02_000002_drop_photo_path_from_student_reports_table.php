<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pas foto 4×6 di raport dihapus.
     *
     * Berkas lama di `storage/app/public/report-photos` sengaja tidak ikut
     * dihapus di sini — migration tidak seharusnya menyentuh disk, dan
     * membuangnya adalah keputusan yang perlu disengaja.
     */
    public function up(): void
    {
        Schema::table('student_reports', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('student_reports', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('tutor_notes');
        });
    }
};
