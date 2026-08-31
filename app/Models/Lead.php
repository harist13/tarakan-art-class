<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Calon murid yang mengisi form Kontak di website publik.
 */
class Lead extends Model
{
    protected $fillable = [
        'child_name',
        'child_age',
        'date_of_birth',
        'class_type',
        'parent_name',
        'parent_phone',
        'parent_email',
        'address',
        'program',
        'message',
        'status',
    ];

    protected $casts = [
        'child_age' => 'integer',
        'date_of_birth' => 'date',
    ];

    /**
     * Tipe kelas untuk sesi liburan.
     *
     * Sengaja hanya berlaku untuk lead: kolom `leads.class_type` bertipe string
     * (calon murid boleh berubah pikiran), sedangkan `students.class_type` tetap
     * enum tiga kategori dan divalidasi terpisah di StudentController. Jadi nilai
     * ini tidak pernah ikut berpindah saat admin mengubah lead menjadi murid.
     */
    public const HOLIDAY_TYPE = 'holiday';

    /** Label tipe kelas untuk ditampilkan ke admin. */
    public const CLASS_TYPES = [
        'preschool' => 'Preschool',
        'coloring' => 'Coloring',
        'drawing' => 'Drawing',
        self::HOLIDAY_TYPE => 'Holiday Class',
    ];

    public function classTypeName(): ?string
    {
        return self::CLASS_TYPES[$this->class_type] ?? $this->class_type;
    }

    /**
     * Nama program yang dipilih (bukan slug-nya), untuk ditampilkan ke admin.
     */
    public function programName(): ?string
    {
        if (! $this->program) {
            return null;
        }

        $program = Arr::first(
            config('site.programs', []),
            fn (array $p) => $p['slug'] === $this->program
        );

        return $program['name'] ?? $this->program;
    }
}
