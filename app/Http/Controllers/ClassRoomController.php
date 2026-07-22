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
            ->orderBy('class_name')
            ->paginate(10)
            ->withQueryString();

        $tutors = Tutor::orderBy('name')->get();

        return view('classes.index', compact('classes', 'search', 'tutors'));
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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        Tutor::create($data);

        return redirect()->route('classes.index')->with('success', 'Tutor berhasil ditambahkan.');
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
