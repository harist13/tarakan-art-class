<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelas adalah slot mingguan berulang, bukan sesi sekali jalan.
     *
     * Sebelumnya jadwal disimpan sebagai satu `schedule_date`. Akibatnya begitu
     * tanggal itu lewat, kelas dianggap kedaluwarsa selamanya: hilang dari
     * dropdown kelas pengganti, dari halaman jadwal publik, dan hanya muncul
     * sekali di kalender. Padahal modul absensi sudah memperlakukannya sebagai
     * slot berulang (punya `attendance_date` sendiri).
     *
     * Jadwal kini disimpan sebagai pola: `day_of_week` + `schedule_time`, dengan
     * `start_date` sebagai tanggal mulai berlakunya slot. Tanggal sesi konkret
     * diturunkan saat dibutuhkan (lihat ClassRoom::occurrencesBetween).
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // 0 = Minggu … 6 = Sabtu, mengikuti Carbon::dayOfWeek.
            $table->unsignedTinyInteger('day_of_week')->default(1)->after('class_category');
        });

        // Backfill: hari mingguan diturunkan dari tanggal jadwal yang lama.
        DB::table('classes')->select('id', 'schedule_date')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('classes')->where('id', $row->id)->update([
                    'day_of_week' => Carbon::parse($row->schedule_date)->dayOfWeek,
                ]);
            }
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('schedule_date', 'start_date');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('start_date', 'schedule_date');
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('day_of_week');
        });
    }
};
