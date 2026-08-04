<?php

namespace App\Models;

use App\Observers\StudentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
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
