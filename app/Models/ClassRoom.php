<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Slot kelas mingguan berulang.
 *
 * Jadwalnya dinyatakan sebagai `schedule_date` + `schedule_time`, ditemani
 * `is_recurring`:
 *
 *   berulang     — kelas berjalan tiap pekan pada hari `schedule_date`, dengan
 *                  tanggal itu sebagai sesi pertamanya. Slot semacam ini tidak
 *                  pernah kedaluwarsa; yang bisa lewat hanya sesinya.
 *   sekali jalan — kelas hanya berjalan pada `schedule_date` itu saja, dan
 *                  memang kedaluwarsa setelah lewat.
 *
 * Tanggal sesi berikutnya tidak disimpan, melainkan diturunkan lewat
 * nextOccurrence() / occurrencesBetween().
 */
class ClassRoom extends Model
{
    protected $table = 'classes';

    /** 0 = Minggu … 6 = Sabtu, mengikuti Carbon::dayOfWeek. */
    public const DAY_NAMES = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    protected $fillable = [
        'class_code',
        'class_category',
        'tutor_id',
        'capacity',
        'schedule_date',
        'is_recurring',
        'schedule_time',
        'class_fee',
        'status',
        'closed_reason',
    ];

    /**
     * Default kolom `is_recurring` ada di database, tapi itu tidak mengisi atribut
     * model yang baru dibuat — tanpa default di sini, kelas baru sempat terbaca
     * sebagai sekali jalan sampai model dimuat ulang.
     */
    protected $attributes = [
        'is_recurring' => true,
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'is_recurring' => 'boolean',
        'class_fee' => 'decimal:2',
    ];

    /**
     * Hari mingguan kelas (0 = Minggu … 6 = Sabtu, mengikuti Carbon::dayOfWeek).
     *
     * Diturunkan, bukan disimpan: kolom tersendiri hanya akan melenceng dari
     * `schedule_date` begitu ada penulisan yang melewati model. Konsekuensinya
     * nilai ini tidak bisa dipakai di WHERE/ORDER BY — penyaringan per hari
     * dilakukan di PHP (lihat ClassRoomController::index).
     */
    protected function dayOfWeek(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->schedule_date?->dayOfWeek);
    }

    protected static function booted(): void
    {
        static::creating(function (ClassRoom $class) {
            if (empty($class->class_code)) {
                $class->class_code = self::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', $last->class_code)) + 1 : 1;

        return 'CLS'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_class', 'class_id', 'student_id')
            ->withPivot(['status', 'enrolled_at'])
            ->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function replacementRequests(): HasMany
    {
        return $this->hasMany(ReplacementRequest::class, 'class_id');
    }

    // ─── Ketersediaan slot ─────────────────────────────────────────

    /**
     * Jumlah murid aktif terdaftar. Memakai `enrolled_count` dari withCount bila tersedia
     * (hindari N+1 di listing), fallback ke query.
     */
    public function enrolledCount(): int
    {
        if (! is_null($this->enrolled_count)) {
            return (int) $this->enrolled_count;
        }

        return $this->students()->wherePivot('status', 'active')->count();
    }

    /**
     * Sisa kursi (tidak pernah negatif).
     */
    public function remainingSeats(): int
    {
        return max(0, $this->capacity - $this->enrolledCount());
    }

    public function isFull(): bool
    {
        return $this->enrolledCount() >= $this->capacity;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Tutor tersedia untuk slot ini (ada & terdaftar).
     *
     * Baik full-time maupun part-time dianggap tersedia.
     */
    public function hasTutor(): bool
    {
        return $this->tutor !== null;
    }

    /**
     * Kategori kelas cocok dengan tipe kelas murid (mis. murid Coloring → slot Coloring).
     *
     * Perbandingannya mengabaikan besar-kecil huruf: kategori kini diketik bebas oleh
     * admin, jadi "Coloring" dan "coloring" harus dianggap tipe yang sama — sama seperti
     * pencocokan di StudentController.
     *
     * Bersifat informatif: murid boleh mengambil replacement lintas tipe, jadi hasil
     * false hanya dipakai untuk menandai "beda tipe" di UI, bukan untuk menolak.
     */
    public function matchesLevel(?string $studentType): bool
    {
        return filled($studentType)
            && filled($this->class_category)
            && mb_strtolower($this->class_category) === mb_strtolower($studentType);
    }

    /**
     * Slot bisa diisi (mis. untuk replacement) bila: dibuka manual, masih ada kursi,
     * tutornya tersedia, dan masih punya sesi mendatang. Ini satu-satunya syarat
     * untuk replacement — kecocokan tipe kelas dinilai terpisah lewat
     * isAvailableFor(), sekadar penanda.
     *
     * Perhatikan tidak ada cek "sudah lewat": slot mingguan tidak kedaluwarsa.
     * Yang bisa lewat adalah sesi tertentu, dan itu dinilai per tanggal.
     */
    public function isAvailable(): bool
    {
        return ! $this->isClosed()
            && ! $this->isFull()
            && $this->hasTutor()
            && $this->nextOccurrence() !== null;
    }

    /**
     * "Pas" untuk murid tertentu: available umum + kategori cocok dengan tipe murid.
     * Dipakai untuk menyorot slot yang paling sesuai, bukan sebagai syarat replacement.
     */
    public function isAvailableFor(Student $student): bool
    {
        return $this->isAvailable() && $this->matchesLevel($student->class_type);
    }

    // ─── Sesi mingguan ─────────────────────────────────────────────

    /** Nama hari slot, mis. "Rabu". */
    public function dayName(): string
    {
        return self::DAY_NAMES[$this->day_of_week] ?? '-';
    }

    /** Jam mulai sebagai "HH:MM". */
    public function timeLabel(): string
    {
        return substr((string) $this->schedule_time, 0, 5);
    }

    /**
     * Label jadwal: "Setiap Rabu, 09:00" untuk kelas berulang, atau
     * "Rabu, 20 Agu 2026, 09:00" untuk kelas sekali jalan.
     */
    public function scheduleLabel(): string
    {
        if (! $this->is_recurring) {
            return $this->dayName().', '.$this->schedule_date->format('d M Y').', '.$this->timeLabel();
        }

        return 'Setiap '.$this->dayName().', '.$this->timeLabel();
    }

    /**
     * Label sesi bertanggal: "Senin, 20 Juli 2026 - 12.00 PM".
     *
     * Berbeda dari scheduleLabel() yang menyebut pola mingguan ("Setiap Senin,
     * 10:00"): ini selalu satu sesi konkret, baik untuk kelas berulang maupun
     * sekali jalan. Dipakai saat yang perlu dijawab adalah "kapan tepatnya",
     * mis. jadwal kelas asal di form replacement.
     *
     * Sesi yang dipakai adalah kejadian berikutnya. Kelas sekali jalan yang
     * sesinya sudah lewat tetap dilabeli dengan tanggal aslinya — itu memang
     * jadwal terakhirnya.
     *
     * Nama hari & bulan dipaksa locale 'id' karena APP_LOCALE aplikasi ini 'en'.
     */
    public function sessionLabel(): ?string
    {
        $at = $this->nextOccurrence()
            ?? ($this->is_recurring ? null : $this->occurrenceAt($this->schedule_date));

        return $at ? self::formatSession($at) : null;
    }

    /**
     * "Senin, 17 Agustus 2026 - 10.00 AM" — satu sesi bertanggal.
     *
     * Dipisah agar daftar sesi (mis. pilihan "tanggal sesi yang dilewatkan" di form
     * replacement) memakai bentuk yang persis sama dengan sessionLabel(), bukan
     * salinan format yang bisa menyimpang sendiri.
     *
     * Locale dipaksa 'id' karena APP_LOCALE aplikasi ini 'en'.
     */
    public static function formatSession(Carbon $at): string
    {
        return $at->locale('id')->translatedFormat('l, j F Y').' - '.$at->format('g.i A');
    }

    /**
     * Gabungkan tanggal sesi dengan jam slot.
     */
    public function occurrenceAt(Carbon $date): Carbon
    {
        $time = $this->schedule_time ? substr($this->schedule_time, 0, 8) : '00:00:00';

        return $date->copy()->setTimeFromTimeString($time);
    }

    /**
     * Tanggal ini adalah sesi kelas ini.
     */
    public function occursOn(Carbon $date): bool
    {
        if (! $this->is_recurring) {
            return $date->toDateString() === $this->schedule_date->toDateString();
        }

        return (int) $date->dayOfWeek === (int) $this->day_of_week
            && $date->toDateString() >= $this->schedule_date->toDateString();
    }

    /**
     * Sesi berikutnya yang belum lewat.
     *
     * Null hanya untuk kelas sekali jalan yang sesinya sudah terlewat — slot
     * mingguan selalu punya sesi berikutnya.
     */
    public function nextOccurrence(?Carbon $from = null): ?Carbon
    {
        $from = $from ? $from->copy() : Carbon::now();

        // Kelas sekali jalan: hanya punya satu sesi, dan memang habis setelah lewat.
        if (! $this->is_recurring) {
            $at = $this->occurrenceAt($this->schedule_date);

            return $at->gte($from) ? $at : null;
        }

        $cursor = $this->firstCandidate($from);
        $at = $this->occurrenceAt($cursor);

        // gte, bukan isFuture: sesi yang jam mulainya persis sekarang masih
        // terhitung. Yang jamnya sudah lewat hari ini digeser sepekan.
        return $at->gte($from) ? $at : $this->occurrenceAt($cursor->addWeek());
    }

    /**
     * Semua sesi dalam rentang tanggal (inklusif).
     *
     * @return list<Carbon>
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        $limit = $to->copy()->endOfDay();

        // Kelas sekali jalan: paling banyak satu sesi, itu pun kalau masuk rentang.
        if (! $this->is_recurring) {
            $at = $this->occurrenceAt($this->schedule_date);
            $masuk = $at->gte($from->copy()->startOfDay()) && $at->lte($limit);

            return $masuk ? [$at] : [];
        }

        $cursor = $this->firstCandidate($from);
        $dates = [];

        while ($cursor->lte($limit)) {
            $dates[] = $this->occurrenceAt($cursor);
            $cursor = $cursor->addWeek();
        }

        return $dates;
    }

    /**
     * Sesi di sekitar hari ini, sebagai pilihan "tanggal sesi yang dilewatkan"
     * di form replacement.
     *
     * Mundur beberapa pekan karena kelas pengganti sering baru diminta setelah
     * anaknya absen, dan maju beberapa pekan untuk absen yang sudah direncanakan.
     *
     * @return array<int, Carbon>
     */
    public function sessionWindow(int $weeksBack = 8, int $weeksAhead = 8): array
    {
        return $this->occurrencesBetween(
            Carbon::today()->subWeeks($weeksBack),
            Carbon::today()->addWeeks($weeksAhead)
        );
    }

    /**
     * Tanggal paling awal yang mungkin jadi sesi bagi kelas berulang: hari kelas
     * yang pertama pada/setelah $from, tapi tak pernah sebelum `schedule_date`.
     *
     * Batas bawah itu yang mencegah kalender menampilkan sesi yang tak pernah ada
     * — jendelanya mundur 45 hari, jadi tanpa batas ini kelas yang baru dibuat
     * tampak sudah berjalan berminggu-minggu.
     */
    private function firstCandidate(Carbon $from): Carbon
    {
        $cursor = $from->copy()->startOfDay();
        $start = $this->schedule_date->copy()->startOfDay();

        if ($cursor->lt($start)) {
            $cursor = $start;
        }

        // Maju ke hari mingguan kelas (0 hari bila sudah tepat).
        return $cursor->addDays(((int) $this->day_of_week - (int) $cursor->dayOfWeek + 7) % 7);
    }

    /**
     * Label + warna bootstrap untuk badge ketersediaan. Urutan cek disengaja:
     * tutup manual (keputusan admin) diutamakan, lalu kondisi terhitung.
     *
     * @return array{text: string, color: string}
     */
    public function availability(): array
    {
        if ($this->isClosed()) {
            $text = $this->closed_reason ? 'Ditutup — '.$this->closed_reason : 'Ditutup';

            return ['text' => $text, 'color' => 'secondary', 'bg' => '#475569'];
        }
        if (! $this->hasTutor()) {
            return ['text' => 'Tutor kosong', 'color' => 'warning', 'bg' => 'rgba(245, 136, 12, 1)'];
        }
        if ($this->isFull()) {
            return ['text' => 'Penuh', 'color' => 'danger', 'bg' => '#DC2626'];
        }
        if ($this->nextOccurrence() === null) {
            // Hanya kelas sekali jalan yang bisa kehabisan sesi; slot mingguan
            // selalu punya sesi berikutnya.
            return ['text' => 'Sudah lewat', 'color' => 'dark', 'bg' => '#475569'];
        }

        return ['text' => $this->remainingSeats().' kursi tersisa', 'color' => 'success', 'bg' => '#15803D'];
    }
}
