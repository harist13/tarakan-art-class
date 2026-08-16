<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ReplacementRequest extends Model
{
    protected $fillable = [
        'student_id',
        'origin_class_id',
        'missed_date',
        'class_id',
        'replacement_date',
        'replacement_time',
        'reason',
        'request_status',
        'approved_by',
    ];

    protected $casts = [
        'missed_date' => 'date',
        'replacement_date' => 'date',
    ];

    /** Request yang sudah disetujui — hanya ini yang mengubah daftar absensi. */
    public function scopeApproved($query)
    {
        return $query->where('request_status', 'approved');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Kelas baru (slot pengganti yang dituju). */
    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    /** Kelas asal — jadwal yang ditinggalkan murid. */
    public function originClass(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'origin_class_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Gabungan tanggal + jam pengganti sebagai Carbon — sejajar dengan
     * ClassRoom::occurrenceAt() agar keduanya dinilai dengan cara yang sama.
     */
    public function scheduledAt(): Carbon
    {
        $time = $this->replacement_time ? substr($this->replacement_time, 0, 8) : '00:00:00';

        return $this->replacement_date->copy()->setTimeFromTimeString($time);
    }

    /**
     * Jadwalnya sudah lewat. Berlaku untuk semua status: pending yang terlewat
     * pun tidak bisa dipakai lagi, jadi sifatnya riwayat — bukan agenda.
     */
    public function isPast(): bool
    {
        return $this->scheduledAt()->isPast();
    }
}
