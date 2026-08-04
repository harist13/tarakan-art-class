<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementRequest extends Model
{
    protected $fillable = [
        'student_id',
        'origin_class_id',
        'class_id',
        'replacement_date',
        'replacement_time',
        'reason',
        'request_status',
        'approved_by',
    ];

    protected $casts = [
        'replacement_date' => 'date',
    ];

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
}
