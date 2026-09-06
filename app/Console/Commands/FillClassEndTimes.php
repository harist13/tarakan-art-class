<?php

namespace App\Console\Commands;

use App\Models\ClassRoom;
use Illuminate\Console\Command;

/**
 * Mengisi jam selesai kelas lama yang tak pernah punya.
 *
 * Kelas yang dibuat sebelum jam selesai jadi isian wajib menyimpannya sebagai
 * null; di kalender ia tergambar sebagai garis setipis nol menit. Perintah ini
 * memberi mereka panjang sesi bawaan sanggar.
 *
 * Perintah ini SENGAJA tidak menggeser jam mulai. Versi pertamanya menyeret tiap
 * kelas ke slot terdekat — masuk akal selama semua kelas diyakini berjalan 1,5
 * jam mulai 09:00, dan salah begitu ternyata tiap kategori punya aturannya
 * sendiri: Preschool Senin 16:00-17:00 akan diseretnya jadi 16:30-18:00, yaitu
 * jadwal yang tidak pernah ada. Jam yang sudah tercatat adalah kenyataan; yang
 * boleh dilengkapi hanya yang memang kosong.
 *
 * Dijalankan manual, bukan lewat migrasi: ia menyentuh jadwal yang sudah
 * diberitahukan ke orang tua murid, jadi harus ada orang yang memutuskan —
 * lihat dulu rencananya dengan --dry-run.
 */
class FillClassEndTimes extends Command
{
    protected $signature = 'classes:fill-end-times {--dry-run : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Isi jam selesai kelas lama yang masih kosong, memakai panjang sesi bawaan';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach (ClassRoom::whereNull('schedule_end_time')->orderBy('id')->get() as $class) {
            $mulai = $class->timeLabel();

            if ($mulai === '') {
                $this->warn("{$class->class_code} dilewati: jam mulainya juga kosong.");

                continue;
            }

            $selesai = ClassRoom::slotEnd($mulai);
            $rows[] = [$class->class_code, $class->class_category, $mulai, $mulai.'-'.$selesai];

            if (! $dryRun) {
                $class->forceFill(['schedule_end_time' => $selesai.':00'])->save();
            }
        }

        if (! $rows) {
            $this->info('Semua kelas sudah punya jam selesai. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->table(['Kode', 'Kategori', 'Sebelum', 'Sesudah'], $rows);
        $this->info($dryRun
            ? count($rows).' kelas akan dilengkapi. Jalankan tanpa --dry-run untuk menyimpan.'
            : count($rows).' kelas dilengkapi.');

        return self::SUCCESS;
    }
}
