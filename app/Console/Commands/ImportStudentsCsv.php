<?php

namespace App\Console\Commands;

use App\Models\ClassRoom;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Tutor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Impor murid, jadwal kelas, dan tagihannya dari spreadsheet lama sanggar (CSV).
 *
 * Tiap kombinasi Course + hari + jam di kolom Jadwal menjadi satu kelas reguler
 * (mingguan berulang), lalu murid didaftarkan ke kelas-kelas itu. Satu sel Jadwal
 * boleh berisi beberapa baris — murid yang ikut dua kali sepekan.
 *
 * Jadwal ditulis gaya percakapan, "Sabtu, 12.30-2", tanpa AM/PM. Jam dibaca
 * mengikuti jam buka sanggar: angka di bawah jam buka berarti siang/sore, jadi
 * "12.30-2" adalah 12:30–14:00 dan "4-5" adalah 16:00–17:00.
 *
 * Murid berstatus "Belum Bayar" dibuatkan invoice Unpaid untuk satu periode,
 * dengan nominal persis seperti kolom "Nominal ditagihkan" — angka itulah yang
 * sudah disampaikan ke orang tua, termasuk yang berbeda dari iuran kelas
 * (potongan saudara, uang pendaftaran). Bulan-bulan berikutnya ditagih seperti
 * biasa lewat tombol Tagihan bulanan.
 *
 * Aman diulang: murid yang namanya sudah ada tidak diubah — data yang sudah
 * disunting admin tidak ditimpa — kelas dengan kategori & jadwal yang sama
 * dipakai ulang, dan murid yang sudah punya invoice untuk periode itu dilewati.
 * Karena itu command ini juga bisa dijalankan ulang hanya untuk menerbitkan
 * tagihan murid yang sudah diimpor sebelumnya.
 *
 * Yang tidak ada di spreadsheet dibiarkan kosong alih-alih ditebak: tanggal
 * lahir & usia, serta tutor — kelas diampu tutor "Belum Ditentukan" sampai admin
 * menggantinya (atau pakai --tutor).
 */
class ImportStudentsCsv extends Command
{
    protected $signature = 'students:import-csv
        {file=storage/app/informasi.csv : Lokasi berkas CSV}
        {--mulai= : Tanggal (Y-m-d) bergabung murid & patokan sesi pertama kelas; bawaan hari ini}
        {--tutor= : ID atau nama tutor untuk kelas baru; bawaan tutor "Belum Ditentukan"}
        {--kapasitas=10 : Kapasitas kelas baru, dinaikkan otomatis bila muridnya lebih banyak}
        {--periode= : Periode tagihan (Y-m) invoice yang diterbitkan; bawaan bulan ini}
        {--jatuh-tempo= : Jatuh tempo invoice (Y-m-d); bawaan hari ini + academic.payment.due_days}
        {--metode=transfer : Metode pembayaran invoice: cash, transfer, virtual_account}
        {--tanpa-tagihan : Jangan terbitkan invoice}
        {--dry-run : Tampilkan rencana tanpa menyimpan}';

    protected $description = 'Impor murid, jadwal kelas reguler, dan invoice dari CSV spreadsheet sanggar';

    public const PLACEHOLDER_TUTOR = 'Belum Ditentukan';

    /** Status pembayaran di spreadsheet yang berarti murid tidak lanjut les. */
    private const INACTIVE_STATUSES = ['off', 'belum konfir lanjut'];

    /** Status pembayaran di spreadsheet yang dibuatkan invoice Unpaid. */
    private const UNPAID_STATUS = 'belum bayar';

    /** @var array<string, ClassRoom> kelas yang dipakai impor ini, per kategori+jadwal */
    private array $classes = [];

    /** @var list<string> */
    private array $warnings = [];

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        try {
            $mulai = $this->option('mulai')
                ? Carbon::createFromFormat('!Y-m-d', $this->option('mulai'))
                : Carbon::today();
        } catch (\Throwable) {
            $this->error('Format --mulai harus Y-m-d, mis. 2026-09-01.');

            return self::FAILURE;
        }

        $capacity = (int) $this->option('kapasitas');
        if ($capacity < 1) {
            $this->error('--kapasitas minimal 1.');

            return self::FAILURE;
        }

        $tagihan = null;
        if (! $this->option('tanpa-tagihan') && ! ($tagihan = $this->invoiceOptions())) {
            return self::FAILURE;
        }

        $rows = $this->readRows($path);
        if ($rows === null) {
            return self::FAILURE;
        }

        $records = array_values(array_filter(array_map(fn (array $row) => $this->parseRow($row), $rows)));
        $fees = $this->feesPerCourse($records);
        $dryRun = (bool) $this->option('dry-run');

        // Seluruh impor satu transaksi: --dry-run menjalankan persis langkah yang
        // sama lalu membatalkannya, jadi rencana yang ditampilkan tidak mungkin
        // berbeda dari yang nanti benar-benar disimpan. Pencacah nomor invoice
        // ikut dibatalkan, jadi dry-run tidak memakan nomor.
        DB::beginTransaction();

        try {
            $tutor = $this->resolveTutor();
            if (! $tutor) {
                DB::rollBack();

                return self::FAILURE;
            }

            [$created, $skipped] = $this->import($records, $fees, $tutor, $mulai, $capacity);
            $invoices = $tagihan ? $this->issueInvoices($records, ...$tagihan) : null;
            $classRows = $this->classSummary();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        $this->table(['Kode', 'Kategori', 'Jadwal', 'Murid', 'Iuran'], $classRows);

        foreach ($this->warnings as $warning) {
            $this->warn($warning);
        }

        if ($skipped) {
            $this->line(count($skipped).' murid sudah ada, datanya tidak diubah: '.implode(', ', $skipped).'.');
        }

        $this->info(($dryRun ? 'Rencana: ' : '')
            ."{$created} murid & ".count($classRows).' kelas reguler (diampu '.$tutor->name.')'
            .($dryRun ? ' akan diimpor.' : ' diimpor.'));

        if ($invoices) {
            $this->reportInvoices($invoices, $tagihan, $dryRun);
        }

        if ($dryRun) {
            $this->info('Jalankan tanpa --dry-run untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /**
     * Periode, jatuh tempo, dan metode invoice; null bila ada yang tidak sah.
     *
     * @return array{period: string, due: string, method: string}|null
     */
    private function invoiceOptions(): ?array
    {
        $period = $this->option('periode') ?: Payment::periodFor();
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Format --periode harus Y-m, mis. 2026-09.');

            return null;
        }

        try {
            $due = Carbon::createFromFormat('!Y-m-d', $this->option('jatuh-tempo') ?: Payment::defaultDueDate());
        } catch (\Throwable) {
            $this->error('Format --jatuh-tempo harus Y-m-d, mis. 2026-09-18.');

            return null;
        }

        // Jatuh tempo yang sudah lewat membuat seluruh invoice langsung terhitung
        // menunggak, dan setelah masa toleransi muridnya ditangguhkan otomatis.
        if ($due->lt(Carbon::today())) {
            $this->error('--jatuh-tempo tidak boleh sebelum hari ini: invoice langsung terhitung menunggak.');

            return null;
        }

        $method = (string) $this->option('metode');
        if (! in_array($method, Payment::methodValues(), true)) {
            $this->error('--metode harus salah satu dari: '.implode(', ', Payment::methodValues()).'.');

            return null;
        }

        return ['period' => $period, 'due' => $due->toDateString(), 'method' => $method];
    }

    /**
     * Baca CSV jadi daftar baris ber-kunci judul kolom.
     *
     * @return list<array<string, string>>|null
     */
    private function readRows(string $path): ?array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');

        if (! $header) {
            fclose($handle);
            $this->error('Berkas CSV kosong.');

            return null;
        }

        // Ekspor spreadsheet kerap menyisakan BOM & spasi di judul ("Pembayaran ").
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), $header);

        $missing = array_diff(['Nama Murid', 'Course', 'Jadwal'], $header);
        if ($missing) {
            fclose($handle);
            $this->error('Kolom wajib tidak ada: '.implode(', ', $missing).'.');

            return null;
        }

        $rows = [];
        while (($data = fgetcsv($handle, escape: '')) !== false) {
            if ($data === [null]) {
                continue;
            }

            $data = array_pad(array_slice($data, 0, count($header)), count($header), '');
            $rows[] = array_map(fn ($v) => trim((string) $v), array_combine($header, $data));
        }

        fclose($handle);

        return $rows;
    }

    /** Satu baris spreadsheet → data murid siap simpan; null untuk baris tanpa nama. */
    private function parseRow(array $row): ?array
    {
        $name = $row['Nama Murid'];
        if ($name === '') {
            return null;
        }

        $schedules = [];
        foreach (preg_split('/\R/', $row['Jadwal']) as $text) {
            if (trim($text) === '') {
                continue;
            }

            $slot = self::parseSchedule($text);
            if ($slot === null) {
                $this->warnings[] = "{$name}: jadwal \"".trim($text).'" tidak terbaca, murid disimpan tanpa kelas itu.';

                continue;
            }

            $schedules[] = $slot;
        }

        $sheetStatus = mb_strtolower($row['Status Pembayaran'] ?? '');

        return [
            'name' => $name,
            'course' => $row['Course'],
            'parent_name' => ($row['Nama Ortu WA'] ?? '') ?: (($row['Nama Transfer'] ?? '') ?: '-'),
            'phone_number' => $this->parsePhone($row['Nomor Hp'] ?? '', $name),
            'instagram_username' => ltrim($row['Instagram Wali'] ?? '', '@') ?: null,
            'status' => in_array($sheetStatus, self::INACTIVE_STATUSES, true) ? 'inactive' : 'active',
            'sheet_status' => $sheetStatus,
            'nominal' => (int) preg_replace('/\D/', '', $row['Nominal ditagihkan'] ?? ''),
            'schedules' => $schedules,
        ];
    }

    /**
     * "Sabtu, 12.30-2" → ['day' => 6, 'start' => '12:30', 'end' => '14:00'].
     *
     * Null bila tak terbaca: hari tak dikenal, jam kosong ("Jumat, "), atau jam
     * selesai tidak lebih malam dari jam mulai.
     *
     * @return array{day: int, start: string, end: string}|null
     */
    public static function parseSchedule(string $text): ?array
    {
        $days = implode('|', ClassRoom::DAY_NAMES);
        $pattern = "/^\s*({$days})\s*,\s*(\d{1,2})(?:[.:](\d{2}))?\s*-\s*(\d{1,2})(?:[.:](\d{2}))?\s*$/iu";

        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $day = array_search(mb_strtolower($m[1]), array_map('mb_strtolower', ClassRoom::DAY_NAMES), true);
        $start = self::clockTime((int) $m[2], (int) ($m[3] ?? 0));
        $end = self::clockTime((int) $m[4], (int) ($m[5] ?? 0));

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return ['day' => $day, 'start' => $start, 'end' => $end];
    }

    /** Jam spreadsheet tanpa AM/PM → "HH:MM" 24 jam, mengikuti jam buka sanggar. */
    private static function clockTime(int $hour, int $minute): ?string
    {
        if ($hour < (int) substr(ClassRoom::SLOT_START, 0, 2)) {
            $hour += 12;
        }

        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    /**
     * Nomor HP ke bentuk yang diterima form murid: angka saja, diawali 0.
     *
     * Sebagian sel berisi dua nomor — "0811 5392 388 (0812 9441 8535)". Kolom
     * murid hanya muat satu: yang pertama disimpan, sisanya dilaporkan.
     */
    private function parsePhone(string $raw, string $name): string
    {
        $parts = preg_split('/[(\/]/', $raw, 2);
        $digits = preg_replace('/\D/', '', $parts[0]);

        if (isset($parts[1]) && preg_match('/\d/', $parts[1])) {
            $this->warnings[] = "{$name}: nomor tambahan \"".trim($parts[1], ' )').'" tidak disimpan.';
        }

        return match (true) {
            $digits === '' => '',
            str_starts_with($digits, '62') => '0'.substr($digits, 2),
            str_starts_with($digits, '8') => '0'.$digits,
            default => $digits,
        };
    }

    /**
     * Iuran bulanan per course: nominal yang paling sering ditagihkan per jadwal.
     *
     * Spreadsheet mencatat tagihan per murid, bukan harga kelas. Murid dua jadwal
     * ditagih dua kali lipat, dan segelintir murid ditagih lain dari biasanya
     * (potongan saudara, uang pendaftaran) — nilai terbanyak itulah harga
     * kelasnya.
     *
     * @return array<string, int>
     */
    private function feesPerCourse(array $records): array
    {
        $perCourse = [];
        foreach ($records as $r) {
            if ($r['nominal'] > 0 && $r['schedules']) {
                $perCourse[mb_strtolower($r['course'])][] = intdiv($r['nominal'], count($r['schedules']));
            }
        }

        return array_map(function (array $amounts) {
            $counts = array_count_values($amounts);
            arsort($counts);

            return (int) array_key_first($counts);
        }, $perCourse);
    }

    private function resolveTutor(): ?Tutor
    {
        $choice = $this->option('tutor');

        if (blank($choice)) {
            return Tutor::firstOrCreate(
                ['name' => self::PLACEHOLDER_TUTOR],
                ['status' => Tutor::STATUS_PART_TIME]
            );
        }

        $tutor = ctype_digit((string) $choice) ? Tutor::find($choice) : Tutor::where('name', $choice)->first();

        if (! $tutor) {
            $this->error("Tutor \"{$choice}\" tidak ditemukan.");
        }

        return $tutor;
    }

    /** @return array{0: int, 1: list<string>} jumlah murid dibuat & nama yang sudah ada */
    private function import(array $records, array $fees, Tutor $tutor, Carbon $mulai, int $capacity): array
    {
        $created = 0;
        $skipped = [];

        foreach ($records as $r) {
            if (Student::whereRaw('LOWER(name) = ?', [mb_strtolower($r['name'])])->exists()) {
                $skipped[] = $r['name'];

                continue;
            }

            $student = Student::create([
                'name' => $r['name'],
                'parent_name' => $r['parent_name'],
                'phone_number' => $r['phone_number'],
                'instagram_username' => $r['instagram_username'],
                'class_type' => $r['course'],
                'status' => $r['status'],
                'join_date' => $mulai->toDateString(),
            ]);
            $created++;

            $enrollments = [];
            foreach ($r['schedules'] as $slot) {
                $fee = $fees[mb_strtolower($r['course'])] ?? 0;
                $class = $this->classFor($r['course'], $slot, $fee, $tutor, $mulai, $capacity);

                // Pekan mulai 1: murid spreadsheet sudah berjalan, bukan anak baru
                // yang masuk di tengah bulan — iuran bulan pertamanya penuh.
                $enrollments[$class->id] = [
                    'status' => $r['status'],
                    'enrolled_at' => $mulai->toDateString(),
                    'start_week' => 1,
                ];
            }

            $student->classes()->attach($enrollments);

            if (! $r['schedules'] && $r['status'] === 'active') {
                $this->warnings[] = "{$r['name']}: aktif tapi tanpa jadwal, belum terdaftar di kelas mana pun.";
            }
        }

        // Kelas baru tak boleh langsung tampil "Penuh" hanya karena kapasitas
        // bawaannya lebih kecil dari murid yang memang sudah ada di jadwal itu.
        foreach ($this->classes as $class) {
            $enrolled = $class->enrolledCount();

            if ($class->wasRecentlyCreated && $enrolled > $class->capacity) {
                $class->update(['capacity' => $enrolled]);
            } elseif ($enrolled > $class->capacity) {
                $this->warnings[] = "{$class->class_code}: {$enrolled} murid melebihi kapasitas {$class->capacity}.";
            }
        }

        return [$created, $skipped];
    }

    /** Kelas reguler untuk course + jadwal ini: yang sudah ada dipakai ulang. */
    private function classFor(string $course, array $slot, int $fee, Tutor $tutor, Carbon $mulai, int $capacity): ClassRoom
    {
        $key = mb_strtolower($course)."|{$slot['day']}|{$slot['start']}|{$slot['end']}";

        return $this->classes[$key] ??= ClassRoom::whereRaw('LOWER(class_category) = ?', [mb_strtolower($course)])
            ->where('class_type', 'regular')
            ->where('schedule_time', $slot['start'].':00')
            ->where('schedule_end_time', $slot['end'].':00')
            ->get()
            // Hari tidak disimpan sebagai kolom — diturunkan dari schedule_date.
            ->first(fn (ClassRoom $c) => $c->day_of_week === $slot['day'])
            ?? ClassRoom::create([
                'class_category' => $course,
                'class_type' => 'regular',
                'is_recurring' => true,
                'tutor_id' => $tutor->id,
                'capacity' => $capacity,
                // Sesi pertama: hari kelas yang pertama pada/setelah tanggal mulai.
                'schedule_date' => $mulai->copy()->addDays(($slot['day'] - $mulai->dayOfWeek + 7) % 7)->toDateString(),
                'schedule_time' => $slot['start'].':00',
                'schedule_end_time' => $slot['end'].':00',
                'class_fee' => $fee,
                // Murid spreadsheet sudah lama terdaftar. Uang pendaftaran di kelas
                // ini akan ikut tertagih di invoice pertama mereka di sistem.
                'registration_fee' => 0,
            ]);
    }

    /**
     * Invoice Unpaid untuk murid "Belum Bayar", satu per murid per periode.
     *
     * Berjalan untuk murid yang baru dibuat maupun yang sudah ada dari impor
     * sebelumnya. Yang sudah punya invoice periode itu dilewati — sama seperti
     * tombol Tagihan bulanan, dan unique index-nya memang menolak duplikat.
     *
     * @return array{created: int, existing: int, total: float, numbers: list<string>}
     */
    private function issueInvoices(array $records, string $period, string $due, string $method): array
    {
        $result = ['created' => 0, 'existing' => 0, 'total' => 0.0, 'numbers' => []];

        foreach ($records as $r) {
            if ($r['sheet_status'] !== self::UNPAID_STATUS) {
                $dikenal = $r['sheet_status'] === '' || in_array($r['sheet_status'], self::INACTIVE_STATUSES, true);
                if (! $dikenal) {
                    $this->warnings[] = "{$r['name']}: status pembayaran \"{$r['sheet_status']}\" tidak dikenal, tidak dibuatkan invoice.";
                }

                continue;
            }

            if ($r['nominal'] <= 0) {
                $this->warnings[] = "{$r['name']}: nominal ditagihkan kosong, tidak dibuatkan invoice.";

                continue;
            }

            $student = Student::whereRaw('LOWER(name) = ?', [mb_strtolower($r['name'])])->first();

            // Murid yang sudah dinonaktifkan admin sejak impor sebelumnya.
            if ($student->status !== 'active') {
                $this->warnings[] = "{$r['name']}: di sistem sudah nonaktif, tidak dibuatkan invoice.";

                continue;
            }

            if (Payment::existingForPeriod($student->id, $period)) {
                $result['existing']++;

                continue;
            }

            $payment = Payment::create([
                'student_id' => $student->id,
                'payment_date' => Carbon::today()->toDateString(),
                'due_date' => $due,
                'billing_period' => $period,
                'payment_amount' => $r['nominal'],
                'payment_method' => $method,
                'payment_status' => 'unpaid',
                'notes' => 'Diimpor dari spreadsheet',
            ]);

            $result['created']++;
            $result['total'] += $r['nominal'];
            $result['numbers'][] = $payment->invoice_number;

            $iuran = (float) $student->classes()->sum('classes.class_fee');
            if ($iuran > 0 && (int) $iuran !== $r['nominal']) {
                $this->warnings[] = "{$r['name']}: invoice memakai nominal spreadsheet ".self::rupiah($r['nominal'])
                    .', iuran kelasnya '.self::rupiah($iuran).'.';
            }
        }

        return $result;
    }

    private function reportInvoices(array $invoices, array $tagihan, bool $dryRun): void
    {
        $label = Payment::labelForPeriod($tagihan['period']);

        if ($invoices['created']) {
            $nomor = $invoices['created'] > 1
                ? reset($invoices['numbers']).'–'.end($invoices['numbers'])
                : $invoices['numbers'][0];

            $this->info(($dryRun ? 'Rencana: ' : '')
                ."{$invoices['created']} invoice Unpaid periode {$label} ({$nomor}, total ".self::rupiah($invoices['total']).')'
                .($dryRun ? ' akan diterbitkan' : ' diterbitkan')
                .", jatuh tempo {$tagihan['due']}, metode ".Payment::METHODS[$tagihan['method']]['short'].'.');
        } else {
            $this->info("Tidak ada invoice baru untuk periode {$label}.");
        }

        if ($invoices['existing']) {
            $this->line("{$invoices['existing']} murid dilewati karena sudah punya invoice periode {$label}.");
        }
    }

    private static function rupiah(float|int $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }

    /** Baris tabel ringkasan, urut Senin → Minggu lalu jam mulai. */
    private function classSummary(): array
    {
        return collect($this->classes)
            ->sortBy(fn (ClassRoom $c) => ($c->day_of_week ?: 7).' '.$c->timeLabel())
            ->map(fn (ClassRoom $c) => [
                $c->class_code,
                $c->class_category,
                $c->scheduleLabel(),
                $c->enrolledCount(),
                self::rupiah($c->class_fee),
            ])
            ->values()
            ->all();
    }
}
