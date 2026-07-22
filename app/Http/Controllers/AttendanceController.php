<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $classId = $request->integer('class_id');
        $date = $request->string('date')->toString();
        $search = $request->string('search')->toString();

        $attendances = Attendance::query()
            ->with(['student', 'classRoom'])
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($date, fn ($q) => $q->whereDate('attendance_date', $date))
            ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")))
            ->orderByDesc('attendance_date')
            ->paginate(15)
            ->withQueryString();

        $classes = ClassRoom::orderBy('class_name')->get();

        return view('attendances.index', compact('attendances', 'classes', 'classId', 'date', 'search'));
    }

    public function create(Request $request)
    {
        $classes = ClassRoom::with('students')->orderBy('class_name')->get();
        $selectedClass = null;
        if ($request->filled('class_id')) {
            $selectedClass = ClassRoom::with(['students' => fn ($q) => $q->where('students.status', 'active')])
                ->find($request->integer('class_id'));
        }

        return view('attendances.create', compact('classes', 'selectedClass'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'exists:students,id'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'permit'])],
            'records.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['records'] as $record) {
                Attendance::updateOrCreate(
                    [
                        'student_id' => $record['student_id'],
                        'class_id' => $data['class_id'],
                        'attendance_date' => $data['attendance_date'],
                    ],
                    [
                        'status' => $record['status'],
                        'notes' => $record['notes'] ?? null,
                        'recorded_by' => auth()->id(),
                    ]
                );
            }

            ActivityLog::record('created', null, 'Input absensi kelas');
        });

        return redirect()->route('attendances.index')->with('success', 'Absensi berhasil disimpan.');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return back()->with('success', 'Data absensi berhasil dihapus.');
    }
}
