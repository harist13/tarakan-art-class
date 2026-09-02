<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassRoomController extends Controller
{
    private const PER_PAGE = 10;

    /**
     * Paginasi manual untuk daftar yang sudah disaring di PHP.
     *
     * Dipakai saat filter "Hari" aktif, karena hari tidak lagi tersimpan sebagai
     * kolom sehingga tak bisa disaring di level query.
     *
     * @param  Collection<int, ClassRoom>  $items
     * @return LengthAwarePaginator<int, ClassRoom>
     */
    private function paginateFiltered(Collection $items, Request $request): LengthAwarePaginator
    {
        $items = $items->values();
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();     // tersedia | penuh | tanpa-tutor | ditutup
        $category = $request->string('category')->toString();  // preschool | coloring | drawing
        // Hari mingguan slot; '' = semua. '0' valid (Minggu), jadi dibandingkan sebagai string.
        $day = $request->string('day')->toString();

        // Subquery jumlah murid aktif per kelas (untuk membandingkan dengan kapasitas).
        $enrolledSql = '(select count(*) from student_class where student_class.class_id = classes.id and student_class.status = ?)';

        $query = ClassRoom::query()
            ->with('tutor')
            ->withCount([
                'students',
                'students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active'),
            ])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('class_category', 'like', "%{$search}%")
                    ->orWhere('class_code', 'like', "%{$search}%");
            }))
            ->when($category !== '', fn ($q) => $q->where('class_category', $category))
            // Status ketersediaan — mengikuti prioritas badge: ditutup > tutor kosong > penuh > tersedia.
            // Tidak ada lagi status "sudah lewat": slot mingguan tidak kedaluwarsa.
            ->when($status === 'ditutup', fn ($q) => $q->where('classes.status', 'closed'))
            ->when($status === 'tanpa-tutor', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereDoesntHave('tutor'))
            ->when($status === 'penuh', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereHas('tutor')
                ->whereRaw("{$enrolledSql} >= classes.capacity", ['active']))
            ->when($status === 'tersedia', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereHas('tutor')
                ->whereRaw("{$enrolledSql} < classes.capacity", ['active']))
            // Kelas yang baru dibuat tampil paling atas; id menurun jadi pemecah
            // kalau ada beberapa kelas yang dibuat pada detik yang sama.
            ->orderByDesc('classes.created_at')
            ->orderByDesc('classes.id');

        // Filter hari disaring di PHP, bukan lewat WHERE: `day_of_week` kini
        // diturunkan dari schedule_date, dan ekspresi hari di SQL berbeda antara
        // MySQL & SQLite — cabang raw per driver berarti cabang produksi tak
        // pernah teruji. Jumlah kelas di sanggar kecil, jadi ini tak terasa.
        //
        // Saat satu hari dipilih, hasilnya diurutkan per jam: pertanyaan yang
        // sedang dijawab adalah "kelas apa saja yang jalan hari ini", jadi yang
        // berguna adalah urutan jadwal — bukan kelas terbaru seperti daftar penuh.
        // Kelas sekali jalan yang tanggalnya sudah lewat ikut disaring keluar:
        // pertanyaannya "apa yang jalan hari Senin", bukan "apa yang pernah jalan".
        // Kelas mingguan selalu punya sesi berikutnya, jadi tak pernah tersaring.
        // Daftar tanpa filter tetap menampilkan semuanya — itu inventaris kelas.
        $classes = ($day !== '' && is_numeric($day))
            ? $this->paginateFiltered(
                $query->get()
                    ->filter(fn (ClassRoom $c) => $c->day_of_week === (int) $day && $c->nextOccurrence() !== null)
                    ->sortBy(fn (ClassRoom $c) => $c->timeLabel()),
                $request
            )
            : $query->paginate(self::PER_PAGE)->withQueryString();

        // Filter panel tutor: cari nama/HP, status, & kelas yang diampu.
        $tutorSearch = $request->string('tutor_search')->toString();
        $tutorClassId = $request->integer('tutor_class');
        $tutorStatus = $request->string('tutor_status')->toString();

        $tutors = Tutor::withCount('classes')
            ->when($tutorSearch, fn ($q) => $q->where(function ($sub) use ($tutorSearch) {
                $sub->where('name', 'like', "%{$tutorSearch}%")
                    ->orWhere('phone_number', 'like', "%{$tutorSearch}%");
            }))
            ->when(in_array($tutorStatus, ['full-time', 'part-time'], true), fn ($q) => $q->where('status', $tutorStatus))
            ->when($tutorClassId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('id', $tutorClassId)))
            // Tutor yang baru ditambahkan tampil paling atas, sama seperti daftar kelas.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        // Daftar semua kelas untuk dropdown filter "kelas yang diampu".
        $allClasses = ClassRoom::orderBy('class_category')->get(['id', 'class_category', 'class_code']);

        // Daftar kategori unik untuk dropdown filter.
        $categories = ClassRoom::query()->distinct()->orderBy('class_category')->pluck('class_category');

        // Panel aktif: 'kelas' (default) atau 'tutor'.
        $tab = $request->string('tab')->toString() === 'tutor' ? 'tutor' : 'kelas';

        return view('classes.index', compact(
            'classes', 'search', 'tutors', 'status', 'category', 'day',
            'tab', 'tutorSearch', 'tutorClassId', 'tutorStatus', 'allClasses', 'categories'
        ));
    }

    public function create()
    {
        $tutors = Tutor::orderBy('name')->get();

        return view('classes.create', compact('tutors'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $class = ClassRoom::create($data);
            ActivityLog::record('created', $class, "Membuat kelas {$class->class_category}");
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dibuat.');
    }

    public function edit(ClassRoom $class)
    {
        $tutors = Tutor::orderBy('name')->get();

        return view('classes.edit', compact('class', 'tutors'));
    }

    public function update(Request $request, ClassRoom $class)
    {
        $data = $this->validateData($request, $class);

        DB::transaction(function () use ($class, $data) {
            $class->update($data);
            ActivityLog::record('updated', $class, "Memperbarui kelas {$class->class_category}");
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(ClassRoom $class)
    {
        DB::transaction(function () use ($class) {
            ActivityLog::record('deleted', $class, "Menghapus kelas {$class->class_category}");
            $class->delete();
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Buka/tutup kelas untuk penerimaan murid & replacement.
     */
    public function toggleStatus(Request $request, ClassRoom $class)
    {
        $closing = $class->status !== 'closed';

        if ($closing) {
            // Alasan opsional tapi disarankan, agar admin lain paham kenapa slot ditutup.
            $reason = $request->validate([
                'closed_reason' => ['nullable', 'string', 'max:255'],
            ])['closed_reason'] ?? null;

            $class->status = 'closed';
            $class->closed_reason = $reason;
        } else {
            $class->status = 'open';
            $class->closed_reason = null; // bersihkan alasan saat slot dibuka kembali
        }

        $label = $closing ? 'ditutup' : 'dibuka';

        DB::transaction(function () use ($class, $label) {
            $class->save();
            ActivityLog::record('updated', $class, "Kelas {$class->class_category} {$label}");
        });

        return back()->with('success', "Kelas {$class->class_category} berhasil {$label}.");
    }

    // ─── Tutor management (nested under Class Management) ──────────

    public function storeTutor(Request $request)
    {
        $data = $this->validateTutor($request);

        DB::transaction(function () use ($data) {
            $tutor = Tutor::create($data);
            ActivityLog::record('created', $tutor, "Menambah tutor {$tutor->name}");
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Tutor berhasil ditambahkan.');
    }

    public function updateTutor(Request $request, Tutor $tutor)
    {
        $data = $this->validateTutor($request);

        DB::transaction(function () use ($tutor, $data) {
            $tutor->update($data);
            ActivityLog::record('updated', $tutor, "Memperbarui tutor {$tutor->name}");
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Data tutor berhasil diperbarui.');
    }

    public function destroyTutor(Tutor $tutor)
    {
        // Tutor yang masih mengampu kelas tidak boleh dihapus.
        if ($tutor->classes()->exists()) {
            return redirect()->route('classes.index', ['tab' => 'tutor'])
                ->with('error', "Tutor {$tutor->name} masih mengampu kelas. Pindahkan kelasnya terlebih dahulu.");
        }

        DB::transaction(function () use ($tutor) {
            ActivityLog::record('deleted', $tutor, "Menghapus tutor {$tutor->name}");
            $tutor->delete();
        });

        return redirect()->route('classes.index', ['tab' => 'tutor'])->with('success', 'Tutor berhasil dihapus.');
    }

    private function validateTutor(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['full-time', 'part-time'])],
        ]);
    }

    private function validateData(Request $request, ?ClassRoom $class = null): array
    {
        return $request->validate([
            'class_category' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'class_category')->ignore($class?->id),
            ],
            'tutor_id' => ['required', 'exists:tutors,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            // Jadwal: tanggal + jam. `day_of_week` sengaja tidak divalidasi karena
            // bukan isian admin — ClassRoom menurunkannya dari schedule_date.
            'schedule_date' => ['required', 'date'],
            'schedule_time' => ['required'],
            'is_recurring' => ['required', 'boolean'],
            'class_fee' => ['required', 'numeric', 'min:0'],
        ], [
            'class_category.unique' => 'Kelas sudah ada.',
            'schedule_date.required' => 'Tanggal kelas belum diisi.',
        ]);
    }
}
