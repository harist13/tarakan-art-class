<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassRoomController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();     // tersedia | penuh | ditutup
        $category = $request->string('category')->toString();  // preschool | coloring | drawing
        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();

        // Subquery jumlah murid aktif per kelas (untuk membandingkan dengan kapasitas).
        $enrolledSql = '(select count(*) from student_class where student_class.class_id = classes.id and student_class.status = ?)';

        $classes = ClassRoom::query()
            ->with('tutor')
            ->withCount([
                'students',
                'students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active'),
            ])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('class_name', 'like', "%{$search}%")
                    ->orWhere('class_code', 'like', "%{$search}%");
            }))
            ->when(in_array($category, ['preschool', 'coloring', 'drawing'], true), fn ($q) => $q->where('class_category', $category))
            // Rentang tanggal jadwal (dari–hingga), keduanya opsional.
            ->when($dateFrom, fn ($q) => $q->whereDate('schedule_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('schedule_date', '<=', $dateTo))
            // Status ketersediaan — "ditutup" berprioritas di atas "penuh" (sesuai badge).
            ->when($status === 'ditutup', fn ($q) => $q->where('classes.status', 'closed'))
            ->when($status === 'penuh', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereRaw("{$enrolledSql} >= classes.capacity", ['active']))
            ->when($status === 'tersedia', fn ($q) => $q->where('classes.status', '!=', 'closed')
                ->whereRaw("{$enrolledSql} < classes.capacity", ['active']))
            ->orderBy('class_name')
            ->paginate(10)
            ->withQueryString();

        // Filter panel tutor: cari nama/HP, status, & kelas yang diampu.
        $tutorSearch = $request->string('tutor_search')->toString();
        $tutorClassId = $request->integer('tutor_class');
        $tutorStatus = $request->string('tutor_status')->toString();

        $tutors = Tutor::withCount('classes')
            ->when($tutorSearch, fn ($q) => $q->where(function ($sub) use ($tutorSearch) {
                $sub->where('name', 'like', "%{$tutorSearch}%")
                    ->orWhere('phone_number', 'like', "%{$tutorSearch}%");
            }))
            ->when(in_array($tutorStatus, ['active', 'inactive'], true), fn ($q) => $q->where('status', $tutorStatus))
            ->when($tutorClassId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('id', $tutorClassId)))
            ->orderBy('name')
            ->get();

        // Daftar semua kelas untuk dropdown filter "kelas yang diampu".
        $allClasses = ClassRoom::orderBy('class_name')->get(['id', 'class_name', 'class_code']);

        // Panel aktif: 'kelas' (default) atau 'tutor'.
        $tab = $request->string('tab')->toString() === 'tutor' ? 'tutor' : 'kelas';

        return view('classes.index', compact(
            'classes', 'search', 'tutors', 'status', 'category', 'dateFrom', 'dateTo',
            'tab', 'tutorSearch', 'tutorClassId', 'tutorStatus', 'allClasses'
        ));
    }

    public function create()
    {
        $tutors = Tutor::where('status', 'active')->orderBy('name')->get();

        return view('classes.create', compact('tutors'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data) {
            $class = ClassRoom::create($data);
            ActivityLog::record('created', $class, "Membuat kelas {$class->class_name}");
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
        $data = $this->validateData($request);

        DB::transaction(function () use ($class, $data) {
            $class->update($data);
            ActivityLog::record('updated', $class, "Memperbarui kelas {$class->class_name}");
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(ClassRoom $class)
    {
        DB::transaction(function () use ($class) {
            ActivityLog::record('deleted', $class, "Menghapus kelas {$class->class_name}");
            $class->delete();
        });

        return redirect()->route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    /**
     * Buka/tutup kelas untuk penerimaan murid & replacement.
     */
    public function toggleStatus(ClassRoom $class)
    {
        $class->status = $class->status === 'closed' ? 'open' : 'closed';
        $label = $class->status === 'closed' ? 'ditutup' : 'dibuka';

        DB::transaction(function () use ($class, $label) {
            $class->save();
            ActivityLog::record('updated', $class, "Kelas {$class->class_name} {$label}");
        });

        return back()->with('success', "Kelas {$class->class_name} berhasil {$label}.");
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
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'class_name' => ['required', 'string', 'max:255'],
            'class_category' => ['required', Rule::in(['preschool', 'coloring', 'drawing'])],
            'tutor_id' => ['required', 'exists:tutors,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'schedule_date' => ['required', 'date'],
            'schedule_time' => ['required'],
            'class_fee' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
