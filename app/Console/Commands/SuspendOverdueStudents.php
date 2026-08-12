<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Student;
use Illuminate\Console\Command;

/**
 * Menangguhkan murid yang tunggakannya sudah lewat masa toleransi, dan
 * mencabut penangguhan begitu tunggakannya beres.
 *
 * Penangguhan hanya mencabut murid dari daftar kelas ke depan (lihat
 * Student::scopeAttendable). Absensi, raport, dan pembayaran lamanya tetap
 * utuh dan tetap terlihat — dendanya kehilangan slot kelas, bukan datanya.
 */
class SuspendOverdueStudents extends Command
{
    protected $signature = 'students:suspend-overdue
                            {--dry-run : Tampilkan perubahan tanpa menyimpannya}';

    protected $description = 'Tangguhkan murid dengan tunggakan lewat masa toleransi, pulihkan yang sudah melunasi';

    public function handle(): int
    {
        $grace = (int) config('academic.payment.grace_days', 14);
        $dryRun = (bool) $this->option('dry-run');

        $suspended = 0;
        $restored = 0;

        // ── Tangguhkan: menunggak lebih lama dari masa toleransi ──
        Student::query()
            ->whereNull('suspended_at')
            ->whereHas('payments', fn ($q) => $q->overdue($grace))
            ->with('payments')
            ->each(function (Student $student) use ($dryRun, &$suspended) {
                $reason = ucfirst((string) $student->paymentBlockReason());
                $this->line("  [tangguhkan] {$student->student_id} {$student->name} — {$reason}");

                if (! $dryRun) {
                    $student->suspend($reason);
                    ActivityLog::record('updated', $student, "Menangguhkan murid {$student->name} — {$reason}");
                }
                $suspended++;
            });

        // ── Pulihkan: sudah tidak punya tagihan lewat jatuh tempo ──
        Student::query()
            ->whereNotNull('suspended_at')
            ->settled()
            ->each(function (Student $student) use ($dryRun, &$restored) {
                $this->line("  [pulihkan]   {$student->student_id} {$student->name}");

                if (! $dryRun) {
                    $student->unsuspend();
                    ActivityLog::record('updated', $student, "Memulihkan murid {$student->name} setelah tunggakan lunas");
                }
                $restored++;
            });

        $this->info(($dryRun ? '[dry-run] ' : '')."Selesai: {$suspended} ditangguhkan, {$restored} dipulihkan (toleransi {$grace} hari).");

        return self::SUCCESS;
    }
}
