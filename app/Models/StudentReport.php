<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReport extends Model
{
    protected $fillable = [
        'student_id',
        'period_start',
        'period_end',
        'activity_notes',
        'achievement_score',
        'tutor_notes',
        'photo_path',
        'credential_key',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'achievement_score' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (StudentReport $report) {
            if (empty($report->credential_key)) {
                $report->credential_key = self::generateCredentialKey();
            }
        });
    }

    public static function generateCredentialKey(): string
    {
        $year = now()->year;
        $prefix = 'TAC-'.$year.'-';
        $last = self::where('credential_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();
        $next = $last ? ((int) substr($last->credential_key, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * URL foto pas siswa (null jika belum ada foto).
     */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
