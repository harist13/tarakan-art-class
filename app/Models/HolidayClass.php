<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Modul: Holiday Class — kelas musiman saat libur sekolah.
 *
 * Sengaja berdiri sendiri dari Class Management (F3): sesi liburan tidak punya
 * tutor tetap, tidak masuk kategori preschool/coloring/drawing, dan tidak diikuti
 * murid berlangganan — sekali sesi, tanpa komitmen bulanan. Karena itu tabelnya
 * pun tidak memakai `classes`.
 *
 * Sesi terdekat dipakai website publik untuk mengisi kartu program "Holiday Class"
 * yang sebelumnya hanya teks statis di config/site.php.
 */
class HolidayClass extends Model
{
    protected $fillable = [
        'class_name',
        'schedule',
        'capacity',
        'price',
    ];

    protected $casts = [
        'schedule' => 'datetime',
        'capacity' => 'integer',
        'price' => 'decimal:2',
    ];

    /**
     * Sesi yang belum lewat, terdekat lebih dulu.
     *
     * Batasnya awal hari, bukan jam sekarang, supaya sesi yang sedang berlangsung
     * hari ini tetap tampil di website sampai harinya berakhir — sama seperti
     * cara halaman Jadwal menyaring slot kelas reguler.
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('schedule', '>=', Carbon::today())->orderBy('schedule');
    }

    /** Sesi yang sudah lewat, terbaru lebih dulu. */
    public function scopePast(Builder $query): void
    {
        $query->where('schedule', '<', Carbon::today())->orderByDesc('schedule');
    }

    public function hasPassed(): bool
    {
        return $this->schedule->lt(Carbon::today());
    }
}
