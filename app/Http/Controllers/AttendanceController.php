<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Rules\StudentPaymentSettled;
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
            // Murid yang pembayarannya belum lunas disembunyikan dari modul akademik.
            ->whereHas('student', fn ($s) => $s->paid())
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($date, fn ($q) => $q->whereDate('attendance_date', $date))
            ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")))
            ->orderByDesc('attendance_date')
            ->paginate(15)
            ->withQueryString();

        $classes = ClassRoom::orderBy('class_name')->get();

        // Jumlah baris yang ditahan karena muridnya belum lunas, sekadar catatan
        // agar admin tidak mengira datanya hilang.
        $hiddenCount = Attendance::whereHas('student', fn ($s) => $s->unpaid())->count();

        return view('attendances.index', compact('attendances', 'classes', 'classId', 'date', 'search', 'hiddenCount'));
    }

    public function create(Request $request)
    {
        $classes = ClassRoom::orderBy('class_name')->get();
        $selectedClass = null;
        $students = collect();
        $blockedStudents = collect();

        if ($request->filled('class_id')) {
            $selectedClass = ClassRoom::find($request->integer('class_id'));

            if ($selectedClass) {
                $enrolled = $selectedClass->students()
                    ->where('students.status', 'active')
                    ->with('payments')
                    ->orderBy('name')
                    ->get();

                // Hanya murid lunas yang boleh diabsen; sisanya ditampilkan
                // sebagai catatan agar admin tahu kenapa mereka tidak ada di daftar.
                [$students, $blockedStudents] = $enrolled->partition->isPaid();
            }
        }

        return view('attendances.create', compact('classes', 'selectedClass', 'students', 'blockedStudents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array'],
            'records.*.student_id' => ['required', 'exists:students,id', new StudentPaymentSettled],
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
