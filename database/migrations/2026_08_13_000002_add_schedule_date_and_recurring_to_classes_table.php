<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal kelas kembali dinyatakan sebagai tanggal, ditemani penanda berulang.
     *
     *   schedule_date — tanggal kelas; untuk kelas berulang ini sesi pertamanya
     *   is_recurring  — dicentang: berulang tiap pekan pada hari tanggal tersebut
     *                   tidak dicentang: hanya berjalan sekali pada tanggal itu
     *
     * `day_of_week` DIPERTAHANKAN, tapi tidak lagi diisi admin — ClassRoom
     * menurunkannya otomatis dari schedule_date setiap kali disimpan. Kolomnya
     * tetap ada supaya filter "Hari" di daftar kelas bisa memakai WHERE biasa,
     * tanpa fungsi tanggal yang berbeda antara MySQL dan SQLite.
     *
     * Ini bukan kembali ke model lama yang bermasalah: dulu tanggal tunggal
     * membuat SEMUA kelas dianggap kedaluwarsa begitu tanggalnya lewat. Sekarang
     * kelas berulang memakai schedule_date hanya sebagai titik mulai, dan hanya
     * kelas sekali-jalan yang memang bisa lewat.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->date('schedule_date')->nullable()->after('class_category');
            $table->boolean('is_recurring')->default(true)->after('schedule_date');
        });

        // Backfill: sesi pertama pada/setelah kelas dibuat yang jatuh di day_of_week.
        DB::table('classes')->select('id', 'day_of_week', 'created_at')->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $cursor = Carbon::parse($row->created_at)->startOfDay();
                    $shift = ((int) $row->day_of_week - (int) $cursor->dayOfWeek + 7) % 7;

                    DB::table('classes')->where('id', $row->id)->update([
                        'schedule_date' => $cursor->addDays($shift)->toDateString(),
                    ]);
                }
            });

        Schema::table('classes', function (Blueprint $table) {
            $table->date('schedule_date')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['schedule_date', 'is_recurring']);
        });
    }
};
