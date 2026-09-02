<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentReport extends Model
{
    protected $fillable = [
        'student_id',
        'period_start',
        'period_end',
        'activity_notes',
        'tutor_notes',
        'credential_key',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
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
        $prefix = 'TAC-';
        $last = self::where('credential_key', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->first();
        $next = $last ? ((int) preg_replace('/\D/', '', substr($last->credential_key, strlen($prefix)))) + 1 : 1;

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Foto karya murid sepanjang periode raport ini.
     *
     * Sengaja bukan relasi foreign key: karya terikat pada murid + tanggal, bukan
     * pada satu raport. Yang menyatukannya adalah rentang periode — jadi karya
     * yang diunggah sebelum raportnya dibuat pun tetap ikut terbawa.
     *
     * @return Builder<Artwork>
     */
    public function artworkQuery(): Builder
    {
        return Artwork::where('student_id', $this->student_id)
            ->inPeriod($this->period_start, $this->period_end)
            ->orderBy('taken_on')
            ->orderBy('id');
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
