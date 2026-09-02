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
     * Nilai khusus yang tidak ada di tabel `classes`: ketersediaannya ditentukan
     * sesi mendatang di modul Holiday Class, bukan oleh kategori kelas reguler.
     */
    public const HOLIDAY_TYPE = 'holiday';

    /**
     * Pilihan tipe kelas pada form kontak: kategori kelas yang ada di Class Management,
     * digabung dengan program yang diiklankan di config, ditutup Holiday Class.
     *
     * Daftarnya tidak bisa lagi ditulis tetap di kode — sejak `classes.class_category`
     * menjadi teks bebas, admin menamai sendiri kategorinya, jadi konstanta tiga nilai
     * akan langsung melenceng begitu ada kategori baru.
     *
     * Program di config tetap ikut walau jadwalnya belum ada: brosurnya sudah tayang di
     * website, jadi orang tua harus tetap bisa menyatakan minat pada program yang belum
     * dijadwalkan — itu justru sinyal yang berguna buat admin. Ejaan dari database
     * didahulukan saat keduanya menyebut kategori yang sama.
     *
     * @return array<string, string> nilai => label
     */
    public static function classTypeOptions(): array
    {
        $fromDatabase = ClassRoom::query()
            ->distinct()
            ->orderBy('class_category')
            ->pluck('class_category');

        return $fromDatabase
            ->concat(collect(config('site.programs', []))->pluck('category'))
            ->filter(fn ($category) => filled($category))
            // Dedup mengabaikan besar-kecil huruf supaya kategori "Coloring" milik admin
            // tidak muncul dua kali bersama slug "coloring" di config. unique() menyimpan
            // kemunculan pertama, dan database disebut lebih dulu — jadi ejaan adminlah
            // yang bertahan.
            ->unique(fn (string $category) => mb_strtolower($category))
            ->mapWithKeys(fn (string $category) => [$category => $category])
            ->put(self::HOLIDAY_TYPE, 'Holiday Class')
            ->all();
    }

    /**
     * Label tipe kelas untuk ditampilkan ke admin.
     *
     * Kategori kelas disimpan apa adanya, jadi nilainya sekaligus labelnya; yang
     * perlu diterjemahkan hanya Holiday Class. Lead lama yang kategorinya sudah
     * dihapus tetap tampil dengan nilai aslinya, bukan kosong.
     */
    public function classTypeName(): ?string
    {
        if ($this->class_type === self::HOLIDAY_TYPE) {
            return 'Holiday Class';
        }

        return $this->class_type;
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
