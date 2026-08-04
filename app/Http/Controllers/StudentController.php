<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $classId = $request->integer('class_id');

        $students = Student::query()
            // `payments` dimuat untuk menandai murid yang terkunci dari modul akademik.
            ->with(['classes', 'payments'])
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('parent_name', 'like', "%{$search}%");
            }))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('status', $status))
            ->when($classId, fn ($q) => $q->whereHas('classes', fn ($c) => $c->where('classes.id', $classId)))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $classes = ClassRoom::orderBy('class_name')->get();

        return view('students.index', compact('students', 'search', 'status', 'classId', 'classes'));
    }

    public function create()
    {
        $classes = ClassRoom::withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_name')->get();

        return view('students.create', compact('classes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $classId = $data['class_id'];
        unset($data['class_id']);

        DB::transaction(function () use ($data, $classId) {
            $student = Student::create($data);
            $this->syncClasses($student, [$classId]);
            ActivityLog::record('created', $student, "Menambahkan murid {$student->name}");
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil ditambahkan.');
    }

    public function edit(Student $student)
    {
        $classes = ClassRoom::withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_name')->get();
        $student->load('classes');

        return view('students.edit', compact('student', 'classes'));
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validateData($request);
        $classId = $data['class_id'];
        unset($data['class_id']);

        DB::transaction(function () use ($student, $data, $classId) {
            $student->update($data);
            $this->syncClasses($student, [$classId]);
            ActivityLog::record('updated', $student, "Memperbarui murid {$student->name}");
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil diperbarui.');
    }

    public function destroy(Student $student)
    {
        // Admin tidak boleh hapus permanen — hanya Super Admin (dijaga di route policy).
        DB::transaction(function () use ($student) {
            ActivityLog::record('deleted', $student, "Menghapus murid {$student->name}");
            $student->delete();
        });

        return redirect()->route('students.index')->with('success', 'Data murid berhasil dihapus.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'parent_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'instagram_username' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'class_type' => ['required', Rule::in(['preschool', 'coloring', 'drawing'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'join_date' => ['required', 'date'],
            'class_id' => ['required', Rule::exists('classes', 'id')],
        ], [
            'class_id.required' => 'Kelas wajib dipilih.',
            'class_id.exists' => 'Kelas yang dipilih tidak valid.',
            'class_type.required' => 'Tipe kelas wajib dipilih.',
            'class_type.in' => 'Tipe kelas yang dipilih tidak valid.',
            'phone_number.regex' => 'No HP Wali harus berupa angka.',
            'date_of_birth.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'age.integer' => 'Usia harus berupa angka.',
            'age.min' => 'Usia tidak boleh kurang dari 0.',
            'age.max' => 'Usia tidak masuk akal (maksimal 120).',
        ]);
    }

    private function syncClasses(Student $student, array $classIds): void
    {
        $classIds = array_filter($classIds);
        $payload = [];
        foreach ($classIds as $id) {
            $payload[$id] = ['status' => 'active', 'enrolled_at' => now()->toDateString()];
        }
        $student->classes()->sync($payload);
    }
}
