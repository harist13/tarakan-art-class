<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutor extends Model
{
    protected $fillable = [
        'name',
        'phone_number',
        'status',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'tutor_id');
    }
}
