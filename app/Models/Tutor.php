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

    /**
     * Nama tutor titipan — peninggalan, bukan cara kerja yang berlaku.
     *
     * Dulu kelas wajib punya tutor, jadi impor CSV membuatkan baris bernama
     * "Belum Ditentukan" untuk memegang kursinya. Sejak `classes.tutor_id` boleh
     * kosong, kekosongan itu dinyatakan sebagai NULL dan baris titipannya
     * dihapus oleh migrasi 2026_09_18_100000.
     *
     * Namanya tetap dikenali di sini supaya basis data yang belum dimigrasikan —
     * atau baris yang diketik ulang admin dengan nama itu — tidak terbaca
     * sebagai kelas yang sudah punya pengajar. Lihat isPlaceholder() dan
     * ClassRoom::needsTutor().
     */
    public const PLACEHOLDER_NAME = 'Belum Ditentukan';

    public const STATUSES = [
        self::STATUS_FULL_TIME => 'Full-Time',
        self::STATUS_PART_TIME => 'Part-Time',
    ];

    protected $fillable = [
        'name',
        'phone_number',
        'status',
    ];

    /**
     * Tutor titipan, bukan orang sungguhan.
     *
     * Dicocokkan tanpa memedulikan besar-kecil huruf & spasi tepi: baris ini
     * bisa saja pernah disunting admin lewat form tutor.
     */
    public function isPlaceholder(): bool
    {
        return strcasecmp(trim((string) $this->name), self::PLACEHOLDER_NAME) === 0;
    }

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
