<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->toString();

        $requests = ReplacementRequest::query()
            ->with(['student', 'classRoom', 'approver'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('request_status', $status))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('classRoom', fn ($c) => $c->where('class_name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Daftar slot kelas + ketersediaan, untuk panel tutup/buka slot di layar Scheduler.
        $slots = ClassRoom::query()
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('schedule_date')
            ->orderBy('schedule_time')
            ->get();

        return view('schedules.index', compact('requests', 'status', 'search', 'slots'));
    }

    /**
     * Kalender gabungan: jadwal kelas reguler + replacement class.
     */
    public function calendar()
    {
        $events = [];

        // Jadwal kelas reguler. Available = indigo; penuh/ditutup = abu-abu.
        $classes = ClassRoom::with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->get();

        foreach ($classes as $class) {
            $available = $class->isAvailable();
            $av = $class->availability();
            $events[] = [
                'title' => $class->class_name.($available ? '' : ' ('.$av['text'].')'),
                'start' => $this->combineDateTime($class->schedule_date, $class->schedule_time),
                'color' => $available ? '#0EA5E9' : '#94A3B8',
                'extendedProps' => [
                    'type' => 'Kelas Reguler',
                    'tutor' => $class->tutor->name ?? '-',
                    'category' => ucfirst($class->class_category),
                    'code' => $class->class_code,
                    'availability' => $av['text'],
                    'available' => $available,
                    'occupancy' => $class->enrolledCount().' / '.$class->capacity,
                ],
            ];
        }

        // Replacement class (warna sesuai status).
        $statusColors = ['pending' => '#F59E0B', 'approved' => '#10B981', 'rejected' => '#EF4444'];
        foreach (ReplacementRequest::with(['student', 'classRoom'])->get() as $req) {
            $events[] = [
                'title' => 'Replacement: '.($req->student->name ?? '-'),
                'start' => $this->combineDateTime($req->replacement_date, $req->replacement_time),
                'color' => $statusColors[$req->request_status] ?? '#6B7280',
                'url' => route('schedules.edit', $req),
                'extendedProps' => [
                    'type' => 'Replacement Class',
                    'status' => ucfirst($req->request_status),
                    'class' => $req->classRoom->class_name ?? '-',
                    'reason' => $req->reason ?: '-',
                ],
            ];
        }

        return view('schedules.calendar', ['events' => $events]);
    }

    /**
     * Gabungkan tanggal (Carbon) + jam (string HH:MM:SS) jadi ISO string.
     */
    private function combineDateTime($date, ?string $time): string
    {
        $iso = $date->format('Y-m-d');

        return $time ? $iso.'T'.substr($time, 0, 8) : $iso;
    }

    public function create()
    {
        $students = Student::where('status', 'active')->orderBy('name')->get();
        $classes = ClassRoom::withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_name')->get();

        return view('schedules.create', compact('students', 'classes'));
    }

    public function store(Request $request)
    {
        $data = $this->validateReplacement($request);

        // Status default Pending untuk request Admin.
        $data['request_status'] = 'pending';

        DB::transaction(function () use ($data) {
            $replacement = ReplacementRequest::create($data);
            ActivityLog::record('created', $replacement, 'Mengajukan replacement class');
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil diajukan (status Pending).');
    }

    public function edit(ReplacementRequest $schedule)
    {
        $students = Student::orderBy('name')->get();
        $classes = ClassRoom::withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_name')->get();

        return view('schedules.edit', ['request' => $schedule, 'students' => $students, 'classes' => $classes]);
    }

    public function update(Request $request, ReplacementRequest $schedule)
    {
        $data = $this->validateReplacement($request, $schedule);

        DB::transaction(function () use ($schedule, $data) {
            $schedule->update($data);
            ActivityLog::record('updated', $schedule, 'Memperbarui replacement class');
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil diperbarui.');
    }

    public function updateStatus(Request $request, ReplacementRequest $schedule)
    {
        $data = $request->validate([
            'request_status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        DB::transaction(function () use ($schedule, $data) {
            $schedule->update([
                'request_status' => $data['request_status'],
                'approved_by' => auth()->id(),
            ]);
            ActivityLog::record('updated', $schedule, "Replacement class {$data['request_status']}");
        });

        return back()->with('success', "Request berhasil di-{$data['request_status']}.");
    }

    public function destroy(ReplacementRequest $schedule)
    {
        DB::transaction(function () use ($schedule) {
            ActivityLog::record('deleted', $schedule, 'Menghapus replacement class');
            $schedule->delete();
        });

        return redirect()->route('schedules.index')->with('success', 'Request replacement class berhasil dihapus.');
    }

    /**
     * Validasi request replacement.
     *
     * Slot tujuan harus AVAILABLE (tidak penuh & tidak ditutup). Saat mengedit,
     * kelas yang sudah dipilih sebelumnya tetap diizinkan meski kini penuh/ditutup.
     */
    private function validateReplacement(Request $request, ?ReplacementRequest $current = null): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'class_id' => [
                'required',
                'exists:classes,id',
                function ($attribute, $value, $fail) use ($current) {
                    // Biarkan kelas yang sama saat edit walau statusnya kini berubah.
                    if ($current && (int) $value === (int) $current->class_id) {
                        return;
                    }

                    $class = ClassRoom::find($value);
                    if ($class && ! $class->isAvailable()) {
                        $reason = $class->isClosed() ? 'sudah ditutup' : 'sudah penuh';
                        $fail("Kelas \"{$class->class_name}\" {$reason} sehingga tidak bisa dijadikan slot pengganti. Silakan pilih slot lain yang tersedia.");
                    }
                },
            ],
            'replacement_date' => ['required', 'date'],
            'replacement_time' => ['required'],
            'reason' => ['nullable', 'string'],
        ]);
    }
}
