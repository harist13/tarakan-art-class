<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassRoom extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'class_code',
        'class_name',
        'class_category',
        'tutor_id',
        'capacity',
        'schedule_date',
        'schedule_time',
        'class_fee',
        'status',
        'closed_reason',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'class_fee' => 'decimal:2',
    ];

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
     * Slot sudah lewat bila tanggal+jam jadwalnya di masa lalu.
     */
    public function isPast(): bool
    {
        return $this->scheduleAt()->isPast();
    }

    /**
     * Daftar tanggal libur (string Y-m-d), dimemo per-request agar tidak N+1.
     */
    protected static ?array $holidayDates = null;

    public static function holidayDates(): array
    {
        if (static::$holidayDates === null) {
            static::$holidayDates = Holiday::pluck('date')
                ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())
                ->all();
        }

        return static::$holidayDates;
    }

    /**
     * Kosongkan memo setelah data libur berubah (dipanggil dari controller).
     */
    public static function flushHolidayCache(): void
    {
        static::$holidayDates = null;
    }

    /**
     * Jadwal slot jatuh pada hari libur / tanggal kelas ditiadakan.
     */
    public function isHoliday(): bool
    {
        return in_array($this->schedule_date->toDateString(), static::holidayDates(), true);
    }

    /**
     * Tutor tersedia untuk slot ini (ada & berstatus aktif).
     */
    public function hasTutor(): bool
    {
        return $this->tutor && $this->tutor->status === 'active';
    }

    /**
     * Kategori kelas cocok dengan tipe kelas murid (mis. murid coloring → slot coloring).
     */
    public function matchesLevel(?string $studentType): bool
    {
        return $studentType !== null && $this->class_category === $studentType;
    }

    /**
     * Slot bisa diisi (mis. untuk replacement) bila: dibuka manual, masih ada kursi,
     * belum lewat, dan tutornya tersedia. Level cocok dinilai relatif ke murid
     * lewat isAvailableFor().
     */
    public function isAvailable(): bool
    {
        return ! $this->isClosed()
            && ! $this->isFull()
            && ! $this->isPast()
            && ! $this->isHoliday()
            && $this->hasTutor();
    }

    /**
     * Available untuk murid tertentu: available umum + kategori cocok dengan tipe murid.
     */
    public function isAvailableFor(Student $student): bool
    {
        return $this->isAvailable() && $this->matchesLevel($student->class_type);
    }

    /**
     * Gabungan tanggal + jam jadwal sebagai Carbon.
     */
    public function scheduleAt(): \Illuminate\Support\Carbon
    {
        $time = $this->schedule_time ? substr($this->schedule_time, 0, 8) : '00:00:00';

        return $this->schedule_date->copy()->setTimeFromTimeString($time);
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

            return ['text' => $text, 'color' => 'secondary'];
        }
        if ($this->isPast()) {
            return ['text' => 'Sudah lewat', 'color' => 'dark'];
        }
        if ($this->isHoliday()) {
            return ['text' => 'Hari libur', 'color' => 'info'];
        }
        if (! $this->hasTutor()) {
            return ['text' => 'Tutor kosong', 'color' => 'warning'];
        }
        if ($this->isFull()) {
            return ['text' => 'Penuh', 'color' => 'danger'];
        }

        return ['text' => $this->remainingSeats().' kursi tersisa', 'color' => 'success'];
    }
}
