<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `day_of_week` dihapus; hari kini diturunkan dari `schedule_date` lewat
     * accessor di ClassRoom. Pemanggilan `$class->day_of_week` tidak berubah.
     *
     * ── KENAPA DIHAPUS ────────────────────────────────────────────────
     *
     * Hari tersimpan dua kali: sebagai kolom, dan tersirat di schedule_date.
     * Yang menjaga keduanya cocok hanya sebuah hook `saving` di model — dan hook
     * itu dilewati oleh query builder (`DB::table('classes')->update(...)`),
     * `saveQuietly()`, `withoutEvents()`, serta UPDATE manual lewat phpMyAdmin.
     *
     * Sekali salah satu jalur itu dipakai untuk menggeser jadwal, satu baris
     * menyimpan dua hal yang bertentangan: schedule_date bilang Rabu, day_of_week
     * bilang Senin. Kalender lalu merender sesi tiap Senin, form menampilkan
     * tanggal Rabu, dan tidak ada error apa pun — salah diam-diam.
     *
     * ── KONSEKUENSINYA ────────────────────────────────────────────────
     *
     * Tanpa kolom itu, hari tidak bisa lagi dipakai di WHERE/ORDER BY. Dulu
     * menyaring hari cuma membandingkan angka tersimpan:
     *
     *     WHERE day_of_week = 1
     *
     * Sekarang database harus menghitung hari dari tanggal, dan tiap driver
     * memakai fungsi sekaligus penomoran yang berbeda:
     *
     *     MySQL (aplikasi) : WHERE DAYOFWEEK(schedule_date) = 2   -- Senin = 2
     *     SQLite (tes)     : WHERE strftime('%w', schedule_date) = '1'  -- Senin = 1
     *
     * Menulisnya sebagai raw SQL berarti bercabang per driver — dan karena tes
     * berjalan di SQLite, cabang MySQL tidak akan pernah dieksekusi satu tes pun.
     * Salah offset di cabang itu membuat filter "Senin" menampilkan kelas Minggu
     * sementara seluruh suite tetap hijau. Karena itu hari disaring di PHP saja:
     * satu jalur kode, sama di mana pun (lihat ClassRoomController::index).
     *
     * ── KALAU SUATU SAAT PERLU DIKEMBALIKAN ───────────────────────────
     *
     * Pemicunya bukan selera, tapi angka: saat `$query->get()` di filter Hari
     * mulai memuat terlalu banyak baris. Puluhan sampai ratusan kelas tidak
     * terasa; baru di kisaran ribuan ini berarti.
     *
     * Kalau itu terjadi, jangan ulangi hook model — pakai generated column, agar
     * database yang menghitung sehingga tak mungkin melenceng:
     *
     *     day_of_week TINYINT AS (DAYOFWEEK(schedule_date) - 1) STORED
     *
     * Ekspresi driver-spesifiknya cukup di definisi skema; query aplikasinya
     * kembali seragam. Ganjalannya: SQLite tidak bisa menambah generated column
     * STORED lewat ALTER TABLE (hanya VIRTUAL, dan itu tak bisa diindeks), jadi
     * migrasinya perlu penanganan khusus untuk lingkungan tes.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('day_of_week')->default(1)->after('is_recurring');
        });

        DB::table('classes')->select('id', 'schedule_date')->orderBy('id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('classes')->where('id', $row->id)->update([
                    'day_of_week' => Carbon::parse($row->schedule_date)->dayOfWeek,
                ]);
            }
        });
    }
};
