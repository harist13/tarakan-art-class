<?php

namespace App\Models;

use App\Observers\StudentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(StudentObserver::class)]
class Student extends Model
{
    protected $fillable = [
        'student_id',
        'name',
        'date_of_birth',
        'age',
        'parent_name',
        'phone_number',
        'instagram_username',
        'address',
        'class_type',
        'status',
        'join_date',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'join_date' => 'date',
        'suspended_at' => 'datetime',
        'age' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Student $student) {
            if (empty($student->student_id)) {
                $student->student_id = self::generateStudentId();
            }
            if (empty($student->join_date)) {
                $student->join_date = now()->toDateString();
            }
        });
    }

    public static function generateStudentId(): string
    {
        $last = self::orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', $last->student_id)) + 1 : 1;

        return 'STD'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Usia murid (tahun penuh). Nilai manual di kolom `age` diprioritaskan;
     * bila kosong, dihitung otomatis dari tanggal lahir.
     */
    public function getAgeAttribute($value): ?int
    {
        return $value !== null ? (int) $value : $this->date_of_birth?->age;
    }

    /** Usia hasil hitung otomatis, mengabaikan nilai manual. */
    public function getCalculatedAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /** True bila usia diisi manual dan berbeda dari hitungan tanggal lahir. */
    public function getHasManualAgeAttribute(): bool
    {
        $manual = $this->attributes['age'] ?? null;

        return $manual !== null && (int) $manual !== $this->calculated_age;
    }

    // ─── Status pembayaran ─────────────────────────────────────────
    //
    // Data akademik murid TIDAK disembunyikan karena tagihan. Kelas ini tatap
    // muka: kalau anaknya datang, kehadirannya harus tetap bisa dicatat. Yang
    // digerbang uang hanyalah hal yang memang bisa ditahan tanpa merusak catatan
    // — kelas pengganti dan akses raport orang tua — plus penangguhan otomatis
    // setelah masa toleransi lewat. Lihat config/academic.php.

    /**
     * Murid menunggak: punya invoice yang lewat jatuh tempo dan belum dibayar.
     * Invoice yang baru terbit dan belum jatuh tempo TIDAK menghitung.
     */
    public function scopeInArrears(Builder $query): Builder
    {
        return $query->whereHas('payments', fn ($q) => $q->overdue());
    }

    /** Kebalikan scopeInArrears: tidak punya tagihan yang lewat jatuh tempo. */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereDoesntHave('payments', fn ($q) => $q->overdue());
    }

    /** Murid yang boleh masuk daftar kelas ke depan: aktif & tidak ditangguhkan. */
    public function scopeAttendable(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereNull('suspended_at');
    }

    /** Invoice yang lewat jatuh tempo. Memakai relasi yang sudah dimuat bila ada. */
    public function arrears()
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->filter->isOverdue()->values();
        }

        return $this->payments()->overdue()->get();
    }

    public function hasArrears(): bool
    {
        return $this->arrears()->isNotEmpty();
    }

    /** Umur tunggakan terlama dalam hari; 0 bila tidak menunggak. */
    public function arrearsDays(): int
    {
        return (int) $this->arrears()->max(fn (Payment $p) => $p->daysOverdue()) ?: 0;
    }

    /** Sudah pernah membayar minimal satu invoice — pendaftarannya sudah aktif. */
    public function isActivated(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains('payment_status', 'paid');
        }

        return $this->payments()->where('payment_status', 'paid')->exists();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Ditangguhkan sistem: keluar dari daftar kelas ke depan, histori tetap utuh. */
    public function suspend(string $reason): void
    {
        $this->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();
    }

    public function unsuspend(): void
    {
        $this->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
    }

    /**
     * Alasan murid ditahan dari kelas pengganti & akses raport; null bila aman.
     * Dipakai di pesan validasi & catatan di layar agar admin paham penyebabnya.
     */
    public function paymentBlockReason(): ?string
    {
        $arrears = $this->arrears();

        if ($arrears->isEmpty()) {
            return null;
        }

        $days = (int) $arrears->max(fn (Payment $p) => $p->daysOverdue());

        return "punya {$arrears->count()} invoice lewat jatuh tempo (terlama {$days} hari)";
    }

    /** Label pendek untuk badge di daftar murid; null bila tidak perlu ditandai. */
    public function paymentBadgeLabel(): ?string
    {
        if ($this->hasArrears()) {
            return 'Menunggak '.$this->arrearsDays().' hari';
        }

        if (! $this->isActivated()) {
            return 'Belum bayar pendaftaran';
        }

        return null;
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'student_class', 'student_id', 'class_id')
            ->withPivot(['status', 'enrolled_at'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function replacementRequests(): HasMany
    {
        return $this->hasMany(ReplacementRequest::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(StudentReport::class);
    }
}
