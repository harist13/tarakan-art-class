<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementRequest extends Model
{
    protected $fillable = [
        'student_id',
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

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
