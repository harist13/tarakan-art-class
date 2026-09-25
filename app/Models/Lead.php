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
     * Tipe kelas pada form kontak — berlaku untuk semua program di form.
     *
     * @var array<string, string> nilai => label
     */
    public const CLASS_TYPES = [
        'regular' => 'Reguler (bulanan)',
        'visit' => 'Visit (sekali datang)',
    ];

    /**
     * Label lama yang masih mungkin tersimpan di lead sebelum form memakai
     * pilihan Reguler/Visit (dulu tipe kelas berisi kategori kelas).
     */
    private const LEGACY_TYPES = [
        'holiday' => 'Holiday Class',
    ];

    /**
     * @return array<string, string> nilai => label
     */
    public static function classTypeOptions(): array
    {
        return self::CLASS_TYPES;
    }

    /**
     * Pilihan program pada form kontak: program tetap di config, tanpa Holiday
     * Class — harganya berbeda tiap sesi, jadi peminatnya diarahkan langsung ke
     * chat WhatsApp admin.
     *
     * @return array<string, string> slug => nama
     */
    public static function programOptions(): array
    {
        return collect(config('site.programs', []))
            ->reject(fn (array $program) => ($program['source'] ?? null) === 'holiday_classes')
            // `form_label` opsional: nama yang lebih jelas bagi orang tua di
            // dropdown, mis. "Sketching / Sketsa". Kartu program tetap pakai `name`.
            ->mapWithKeys(fn (array $program) => [$program['slug'] => $program['form_label'] ?? $program['name']])
            ->all();
    }

    /**
     * Label tipe kelas untuk ditampilkan ke admin. Lead lama yang nilainya
     * berupa kategori kelas tetap tampil dengan nilai aslinya, bukan kosong.
     */
    public function classTypeName(): ?string
    {
        return self::CLASS_TYPES[$this->class_type]
            ?? self::LEGACY_TYPES[$this->class_type]
            ?? $this->class_type;
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
