<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutor extends Model
{
    public const STATUS_FULL_TIME = 'full-time';
    public const STATUS_PART_TIME = 'part-time';

    public const STATUSES = [
        self::STATUS_FULL_TIME => 'Full-Time',
        self::STATUS_PART_TIME => 'Part-Time',
    ];

    protected $fillable = [
        'name',
        'phone_number',
        'status',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'tutor_id');
    }
}
