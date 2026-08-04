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

    // ─── Status pembayaran (gerbang akses modul akademik) ──────────

    /**
     * Murid dianggap LUNAS bila punya minimal satu invoice berstatus paid
     * dan tidak menyisakan invoice unpaid. Murid yang belum pernah dibuatkan
     * invoice ikut terhitung belum lunas.
     *
     * Hanya murid lunas yang boleh muncul & diproses di modul akademik
     * (absensi, raport, replacement class).
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->whereHas('payments', fn ($q) => $q->where('payment_status', 'paid'))
            ->whereDoesntHave('payments', fn ($q) => $q->where('payment_status', 'unpaid'));
    }

    /** Kebalikan scopePaid: belum pernah lunas ATAU masih punya tunggakan. */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereDoesntHave('payments', fn ($p) => $p->where('payment_status', 'paid'))
                ->orWhereHas('payments', fn ($p) => $p->where('payment_status', 'unpaid'));
        });
    }

    /** Versi per-instance dari scopePaid. Memakai relasi yang sudah dimuat bila ada. */
    public function isPaid(): bool
    {
        if ($this->relationLoaded('payments')) {
            $statuses = $this->payments->pluck('payment_status');

            return $statuses->contains('paid') && ! $statuses->contains('unpaid');
        }

        return $this->payments()->where('payment_status', 'paid')->exists()
            && ! $this->payments()->where('payment_status', 'unpaid')->exists();
    }

    /**
     * Alasan murid terkunci dari modul akademik; null bila sudah lunas.
     * Dipakai di pesan validasi & catatan di layar agar admin paham penyebabnya.
     */
    public function paymentBlockReason(): ?string
    {
        if ($this->isPaid()) {
            return null;
        }

        $unpaid = $this->relationLoaded('payments')
            ? $this->payments->where('payment_status', 'unpaid')->count()
            : $this->payments()->where('payment_status', 'unpaid')->count();

        return $unpaid > 0
            ? "masih punya {$unpaid} invoice belum lunas"
            : 'belum pernah dibuatkan invoice';
    }

    /** Label pendek untuk badge di daftar murid. */
    public function paymentBadgeLabel(): string
    {
        $hasUnpaid = $this->relationLoaded('payments')
            ? $this->payments->contains('payment_status', 'unpaid')
            : $this->payments()->where('payment_status', 'unpaid')->exists();

        return $hasUnpaid ? 'Belum lunas' : 'Belum ada invoice';
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
