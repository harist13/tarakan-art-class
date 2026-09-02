<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Artwork;
use App\Models\Student;
use App\Models\StudentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Galeri Karya — arsip foto karya murid.
 *
 * Tiga tingkat, mengikuti pola folder yang sudah dipakai Raport Siswa:
 *   index            → folder bulan (semua murid)
 *   index?month=…    → folder murid di dalam bulan itu
 *   folder/{…}       → isi folder: foto karya satu murid pada satu bulan
 *
 * Foldernya sendiri tidak disimpan — dikelompokkan dari `taken_on`, jadi tidak
 * ada folder kosong yang perlu dibereskan admin.
 */
class ArtworkController extends Controller
{
    /** Berapa foto yang boleh diunggah sekaligus dalam satu batch. */
    private const MAX_BATCH = 12;

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $month = $request->string('month')->toString();

        // Mode folder murid: satu kartu per murid yang punya karya di bulan ini.
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $folders = Artwork::query()
                ->with('student')
                ->inMonth($month)
                ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")))
                ->orderByDesc('taken_on')
                ->orderByDesc('id')
                ->get()
                ->groupBy('student_id')
                ->map(fn ($karya) => [
                    'student' => $karya->first()->student,
                    'total' => $karya->count(),
                    // Karya terbaru jadi sampul folder — urutan di atas sudah menaruhnya di depan.
                    'cover' => $karya->first(),
                ])
                ->sortBy(fn (array $folder) => $folder['student']?->name ?? '')
                ->values();

            return view('artworks.index', compact('folders', 'month', 'search'));
        }

        // Mode default: folder bulan.
        $monthExpr = Artwork::monthExpression();

        $months = Artwork::query()
            ->selectRaw("{$monthExpr} as month, COUNT(*) as total, COUNT(DISTINCT student_id) as students")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        return view('artworks.index', compact('months', 'search'));
    }

    /**
     * Form unggah. Murid & bulan bisa diisi dari query agar tombol "Tambah Karya"
     * di dalam folder tidak memaksa admin memilih ulang apa yang sudah jelas.
     */
    public function create(Request $request)
    {
        $students = Student::attendable()->orderBy('name')->get();

        $month = $request->string('month')->toString();
        $studentId = $request->integer('student_id') ?: null;

        // Tanggal default: hari ini bila bulannya memang bulan berjalan, selain
        // itu tanggal 1 bulan tersebut — menebak hari lain hanya akan salah.
        $defaultDate = Carbon::today()->toDateString();
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $awal = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $defaultDate = $awal->isSameMonth(Carbon::today())
                ? Carbon::today()->toDateString()
                : $awal->toDateString();
        }

        return view('artworks.create', compact('students', 'studentId', 'defaultDate', 'month'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'taken_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'photos' => ['required', 'array', 'max:'.self::MAX_BATCH],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'photos.required' => 'Pilih minimal satu foto karya.',
            'photos.max' => 'Maksimal '.self::MAX_BATCH.' foto sekali unggah.',
            'photos.*.image' => 'Semua berkas harus berupa gambar.',
            'photos.*.max' => 'Ukuran tiap foto maksimal 4MB.',
            'taken_on.before_or_equal' => 'Tanggal karya tidak boleh di masa depan.',
        ]);

        // Simpan file dulu, di luar transaksi: menulis ke disk tidak bisa
        // di-rollback, jadi kalau DB gagal file-nya dibersihkan manual (catch).
        $paths = [];
        foreach ($request->file('photos') as $photo) {
            $paths[] = $photo->store('artworks', 'public');
        }

        $student = Student::findOrFail($data['student_id']);

        try {
            DB::transaction(function () use ($data, $paths, $student) {
                foreach ($paths as $path) {
                    Artwork::create([
                        'student_id' => $data['student_id'],
                        'photo_path' => $path,
                        'taken_on' => $data['taken_on'],
                        'description' => $data['description'] ?? null,
                        'created_by' => auth()->id(),
                    ]);
                }

                ActivityLog::record('created', $student, count($paths)." foto karya {$student->name} ditambahkan");
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            throw $e;
        }

        $month = Carbon::parse($data['taken_on'])->format('Y-m');

        return redirect()->route('artworks.folder', ['student' => $student, 'month' => $month])
            ->with('success', count($paths).' foto karya berhasil ditambahkan.');
    }

    /**
     * Isi satu folder: karya seorang murid pada satu bulan.
     */
    public function folder(Student $student, string $month)
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month), 404);

        $artworks = $student->artworks()
            ->with('creator')
            ->inMonth($month)
            ->orderByDesc('taken_on')
            ->orderByDesc('id')
            ->get();

        // Raport bulan yang sama, bila sudah dibuat — dari sini credential key-nya
        // bisa langsung dilihat & disalin admin tanpa pindah modul.
        $report = StudentReport::where('student_id', $student->id)
            ->whereYear('period_start', substr($month, 0, 4))
            ->whereMonth('period_start', substr($month, 5, 2))
            ->first();

        return view('artworks.folder', compact('student', 'month', 'artworks', 'report'));
    }

    /**
     * Ubah tanggal / deskripsi satu foto.
     *
     * Tanggal ikut bisa diubah karena itu yang menentukan foldernya — satu foto
     * yang salah tanggal jadi tersangkut di bulan yang keliru.
     */
    public function update(Request $request, Artwork $artwork)
    {
        $data = $request->validate([
            'taken_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'taken_on.before_or_equal' => 'Tanggal karya tidak boleh di masa depan.',
        ]);

        DB::transaction(function () use ($artwork, $data) {
            $artwork->update($data);
            ActivityLog::record('updated', $artwork->student, "Memperbarui keterangan karya {$artwork->student->name}");
        });

        return redirect()->route('artworks.folder', ['student' => $artwork->student_id, 'month' => $artwork->fresh()->month()])
            ->with('success', 'Keterangan karya berhasil diperbarui.');
    }

    public function destroy(Artwork $artwork)
    {
        $path = $artwork->photo_path;
        $student = $artwork->student;
        $month = $artwork->month();

        DB::transaction(function () use ($artwork, $student) {
            ActivityLog::record('deleted', $student, "Menghapus satu foto karya {$student?->name}");
            $artwork->delete();
        });

        // Hapus file hanya setelah commit sukses.
        Storage::disk('public')->delete($path);

        return redirect()->route('artworks.folder', ['student' => $artwork->student_id, 'month' => $month])
            ->with('success', 'Foto karya berhasil dihapus.');
    }
}
