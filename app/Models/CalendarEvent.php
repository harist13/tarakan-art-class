<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = [
        'title',
        'date',
        'start_time',
        'end_time',
        'description',
        'color',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Acara tanpa jam mulai = acara seharian.
     */
    public function isAllDay(): bool
    {
        return empty($this->start_time);
    }
}
