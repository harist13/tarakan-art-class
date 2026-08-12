<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HolidayClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul: Holiday Class — pengumuman kelas musiman saat libur sekolah.
 *
 * Sesi yang disimpan di sini langsung tampil di website publik (kartu program
 * "Holiday Class" dan kotak Pengumuman di halaman Jadwal), jadi admin bisa
 * mengumumkan sesi liburan tanpa perlu mengubah config & deploy.
 *
 * Berbeda dari Inventaris (F10) dan Pembayaran (F6), modul ini tidak mencatat
 * apa pun ke Laporan Keuangan: `price` baru berupa harga yang diumumkan, belum
 * ada uang yang berpindah. Pemasukannya dicatat lewat modul Pembayaran saat
 * peserta benar-benar membayar.
 */
class HolidayClassController extends Controller
{
    /** Pilihan filter periode pada halaman daftar. */
    private const FILTERS = ['upcoming', 'past', 'all'];

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $filter = $request->string('filter')->toString();
        if (! in_array($filter, self::FILTERS, true)) {
            $filter = 'upcoming';
        }

        $query = HolidayClass::query()
            ->when($search, fn ($q) => $q->where('class_name', 'like', "%{$search}%"));

        // Sesi mendatang diurutkan menaik (yang paling dekat lebih dulu) karena itu
        // yang sedang dikerjakan admin; riwayat diurutkan menurun.
        match ($filter) {
            'upcoming' => $query->upcoming(),
            'past' => $query->past(),
            default => $query->orderByDesc('schedule'),
        };

        $upcoming = HolidayClass::upcoming()->get();

        return view('holiday-classes.index', [
            'classes' => $query->paginate(10)->withQueryString(),
            'search' => $search,
            'filter' => $filter,
            'upcomingCount' => $upcoming->count(),
            'upcomingSeats' => $upcoming->sum('capacity'),
            'next' => $upcoming->first(),
        ]);
    }

    public function create()
    {
        return view('holiday-classes.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $class = DB::transaction(function () use ($data) {
            $class = HolidayClass::create($data);
            ActivityLog::record('created', $class, "Menjadwalkan Holiday Class {$class->class_name}");

            return $class;
        });

        return redirect()->route('holiday-classes.index')
            ->with('success', "Holiday Class \"{$class->class_name}\" berhasil dijadwalkan dan langsung tampil di website.");
    }

    public function edit(HolidayClass $holidayClass)
    {
        return view('holiday-classes.edit', ['class' => $holidayClass]);
    }

    public function update(Request $request, HolidayClass $holidayClass)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($holidayClass, $data) {
            $holidayClass->update($data);
            ActivityLog::record('updated', $holidayClass, "Memperbarui Holiday Class {$holidayClass->class_name}");
        });

        return redirect()->route('holiday-classes.index')
            ->with('success', 'Holiday Class berhasil diperbarui.');
    }

    public function destroy(HolidayClass $holidayClass)
    {
        DB::transaction(function () use ($holidayClass) {
            ActivityLog::record('deleted', $holidayClass, "Menghapus Holiday Class {$holidayClass->class_name}");
            $holidayClass->delete();
        });

        return redirect()->route('holiday-classes.index')
            ->with('success', 'Holiday Class berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'class_name' => ['required', 'string', 'max:255'],
            'schedule' => ['required', 'date'],
            'capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'price' => ['required', 'numeric', 'min:0'],
        ], [], [
            'class_name' => 'nama sesi',
            'schedule' => 'jadwal',
            'capacity' => 'kapasitas',
            'price' => 'biaya',
        ]);
    }
}
