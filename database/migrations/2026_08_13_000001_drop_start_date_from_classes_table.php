<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `start_date` dihapus — satu field isian yang tidak perlu.
     *
     * Kolom ini hanya berperan sebagai batas bawah saat menghitung sesi mingguan,
     * supaya kelas tidak dianggap sudah berjalan sebelum ia ada. Peran itu bisa
     * diambil `created_at` yang sudah terisi otomatis dan artinya sama persis,
     * jadi admin tak perlu mengisinya sendiri (lihat ClassRoom::firstCandidate).
     *
     * Kelas yang baru dibuka nanti ditangani lewat status Ditutup/Dibuka, bukan
     * lewat tanggal mulai.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // Nullable, tidak seperti aslinya: nilai asli sudah tidak ada lagi,
            // jadi yang bisa dipulihkan hanya perkiraan dari created_at.
            $table->date('start_date')->nullable()->after('day_of_week');
        });

        DB::table('classes')->update([
            'start_date' => DB::raw('date(created_at)'),
        ]);
    }
};
