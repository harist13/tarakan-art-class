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
     * Slot bisa diisi (mis. untuk replacement) bila dibuka & masih ada kursi.
     */
    public function isAvailable(): bool
    {
        return ! $this->isClosed() && ! $this->isFull();
    }

    /**
     * Label + warna bootstrap untuk badge ketersediaan.
     *
     * @return array{text: string, color: string}
     */
    public function availability(): array
    {
        if ($this->isClosed()) {
            return ['text' => 'Ditutup', 'color' => 'secondary'];
        }
        if ($this->isFull()) {
            return ['text' => 'Penuh', 'color' => 'danger'];
        }

        return ['text' => $this->remainingSeats().' kursi tersisa', 'color' => 'success'];
    }
}
