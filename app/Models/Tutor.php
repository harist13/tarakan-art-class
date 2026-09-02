<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    // ─── Murid yang diampu ───────────────────────────────────
    //
    // Tutor tidak punya kaitan langsung ke murid: hubungannya lewat kelas
    // (tutors → classes → student_class → students), jadi tidak ada satu relasi
    // Eloquent yang bisa menyatakannya. Scope di bawah memuat rantai itu sekali
    // untuk seluruh daftar, dan activeStudents() merangkumnya di PHP.

    /**
     * Muat kelas beserta murid aktifnya — pasangan wajib activeStudents().
     *
     * Filter pivot 'active' ditaruh di sini, bukan di pemanggil, supaya tidak
     * ada tempat yang memuat relasinya tanpa filter lalu diam-diam ikut
     * menghitung murid yang sudah keluar dari kelas.
     */
    public function scopeWithActiveStudents(Builder $query): Builder
    {
        return $query->with([
            'classes' => fn ($q) => $q->orderBy('class_category'),
            'classes.students' => fn ($q) => $q->wherePivot('status', 'active')->orderBy('name'),
        ]);
    }

    /**
     * Murid aktif yang diampu tutor ini, tanpa duplikat.
     *
     * Satu murid bisa terdaftar di lebih dari satu kelas tutor yang sama, dan
     * yang ditanya admin adalah "berapa anak yang dipegang Kak Sari", bukan
     * "berapa baris pendaftaran" — jadi dihitung unik per murid.
     *
     * @return Collection<int, Student>
     */
    public function activeStudents(): Collection
    {
        $classes = $this->relationLoaded('classes')
            ? $this->classes
            : $this->newQuery()->withActiveStudents()->find($this->id)->classes;

        return $classes->pluck('students')->flatten()->unique('id')->sortBy('name')->values();
    }

    /** Jumlah murid aktif yang diampu — angka pada badge daftar tutor. */
    public function activeStudentCount(): int
    {
        return $this->activeStudents()->count();
    }
}
