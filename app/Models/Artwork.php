<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu foto karya murid.
 *
 * "Folder" bukan baris tersendiri melainkan pengelompokan (murid × bulan) yang
 * diturunkan dari `taken_on` — lihat migration untuk alasannya.
 */
class Artwork extends Model
{
    protected $fillable = [
        'student_id',
        'photo_path',
        'taken_on',
        'description',
        'created_by',
    ];

    protected $casts = [
        'taken_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ekspresi SQL "YYYY-MM" dari `taken_on`, untuk GROUP BY folder bulan.
     *
     * Bentuknya berbeda antara SQLite (dipakai test) dan MySQL, jadi dipusatkan
     * di sini alih-alih ditulis ulang di tiap query.
     */
    public static function monthExpression(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', taken_on)"
            : "DATE_FORMAT(taken_on, '%Y-%m')";
    }

    /**
     * Isi satu folder bulan, mis. '2026-09'.
     */
    public function scopeInMonth(Builder $query, string $month): Builder
    {
        [$year, $bulan] = explode('-', $month);

        return $query->whereYear('taken_on', $year)->whereMonth('taken_on', $bulan);
    }

    /**
     * Karya yang dibuat dalam rentang periode sebuah raport.
     */
    public function scopeInPeriod(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('taken_on', [$from->toDateString(), $to->toDateString()]);
    }

    /** Folder bulan tempat karya ini berada, mis. "2026-09". */
    public function month(): string
    {
        return $this->taken_on->format('Y-m');
    }

    public function photoUrl(): string
    {
        return asset('storage/'.$this->photo_path);
    }
}
