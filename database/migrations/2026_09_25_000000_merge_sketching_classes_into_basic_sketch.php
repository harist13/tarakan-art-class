<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kategori kelas "Sketching" dihapus; semuanya menjadi "Basic Sketch".
     *
     * "Sketching" tetap nama program di situs publik (config/site.php) — yang
     * dihapus hanya kategori kelasnya, yang selama ini isinya sama dengan Basic
     * Sketch dan membuat daftar kelas tampak dobel.
     *
     * Kelas Sketching yang punya kembaran Basic Sketch di hari & jam yang sama
     * dilebur ke kembarannya: pendaftaran murid, absensi, dan kelas pengganti
     * dipindah lebih dulu, baru kelasnya dihapus (tanpa itu cascade ikut
     * menghapus absensinya). Yang tidak punya kembaran cukup diganti kategorinya.
     */
    public function up(): void
    {
        $targets = DB::table('classes')->where('class_category', 'Basic Sketch')->get();

        foreach (DB::table('classes')->where('class_category', 'Sketching')->get() as $source) {
            $target = $targets->first(fn ($t) => $this->sameSlot($t, $source));

            if (! $target) {
                DB::table('classes')->where('id', $source->id)->update(['class_category' => 'Basic Sketch']);

                continue;
            }

            DB::transaction(function () use ($source, $target) {
                // Murid yang sudah terdaftar di kelas tujuan tidak didaftarkan dua kali.
                foreach (DB::table('student_class')->where('class_id', $source->id)->get() as $row) {
                    $exists = DB::table('student_class')
                        ->where('class_id', $target->id)->where('student_id', $row->student_id)->exists();

                    $exists
                        ? DB::table('student_class')->where('id', $row->id)->delete()
                        : DB::table('student_class')->where('id', $row->id)->update(['class_id' => $target->id]);
                }

                foreach (DB::table('attendances')->where('class_id', $source->id)->get() as $row) {
                    $exists = DB::table('attendances')
                        ->where('class_id', $target->id)
                        ->where('student_id', $row->student_id)
                        ->whereDate('attendance_date', $row->attendance_date)
                        ->exists();

                    $exists
                        ? DB::table('attendances')->where('id', $row->id)->delete()
                        : DB::table('attendances')->where('id', $row->id)->update(['class_id' => $target->id]);
                }

                DB::table('replacement_requests')->where('class_id', $source->id)->update(['class_id' => $target->id]);
                DB::table('replacement_requests')->where('origin_class_id', $source->id)->update(['origin_class_id' => $target->id]);

                DB::table('classes')->where('id', $source->id)->delete();
            });
        }

        DB::table('students')->where('class_type', 'Sketching')->update(['class_type' => 'Basic Sketch']);
    }

    public function down(): void
    {
        // Tidak bisa dibalik: setelah dilebur, tidak ada lagi penanda murid mana
        // yang dulunya terdaftar di kelas Sketching.
    }

    /** Hari mingguan, jam mulai, dan jam selesai sama. */
    private function sameSlot(object $a, object $b): bool
    {
        return Carbon::parse($a->schedule_date)->dayOfWeek === Carbon::parse($b->schedule_date)->dayOfWeek
            && substr((string) $a->schedule_time, 0, 5) === substr((string) $b->schedule_time, 0, 5)
            && substr((string) $a->schedule_end_time, 0, 5) === substr((string) $b->schedule_end_time, 0, 5);
    }
};
