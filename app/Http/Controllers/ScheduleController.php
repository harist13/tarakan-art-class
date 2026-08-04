<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\ClassRoom;
use App\Models\Holiday;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Rules\StudentPaymentSettled;
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
            ->with(['student', 'classRoom', 'originClass', 'approver'])
            // Request milik murid yang belum lunas disembunyikan dari modul akademik.
            ->whereHas('student', fn ($s) => $s->paid())
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('request_status', $status))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('classRoom', fn ($c) => $c->where('class_name', 'like', "%{$search}%"))
                    ->orWhereHas('originClass', fn ($c) => $c->where('class_name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Daftar slot kelas + ketersediaan, untuk panel tutup/buka slot di layar Scheduler.
        $slots = ClassRoom::query()
            ->with('tutor')
            ->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('schedule_date')
            ->orderBy('schedule_time')
            ->get();

        // Hari libur untuk panel pengelolaan (yang akan datang di atas).
        $holidays = Holiday::orderBy('date')->get();

        // Acara / agenda umum untuk panel pengelolaan.
        $calendarEvents = CalendarEvent::orderBy('date')->orderBy('start_time')->get();

        // Ringkasan untuk scorecard di atas halaman.
        $pendingCount = ReplacementRequest::where('request_status', 'pending')
            ->whereHas('student', fn ($s) => $s->paid())
            ->count();
        $availableSlots = $slots->filter->isAvailable()->count();

        // Jumlah request yang ditahan karena muridnya belum lunas, sekadar catatan
        // agar admin tidak mengira datanya hilang.
        $hiddenCount = ReplacementRequest::whereHas('student', fn ($s) => $s->unpaid())->count();

        return view('schedules.index', compact(
            'requests', 'status', 'search', 'slots', 'holidays', 'calendarEvents',
            'pendingCount', 'availableSlots', 'hiddenCount'
        ));
    }

    /**
     * Tambah acara / agenda umum ke kalender.
     */
    public function storeEvent(Request $request)
    {
        $data = $request->validateWithBag('event', [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        DB::transaction(function () use ($data) {
            $event = CalendarEvent::create($data);
            ActivityLog::record('created', $event, "Menambah acara \"{$event->title}\"");
        });

        return back()->with('success', 'Acara berhasil ditambahkan ke kalender.');
    }

    /**
     * Hapus acara / agenda.
     */
    public function destroyEvent(CalendarEvent $event)
    {
        DB::transaction(function () use ($event) {
            ActivityLog::record('deleted', $event, "Menghapus acara \"{$event->title}\"");
            $event->delete();
        });

        return back()->with('success', 'Acara berhasil dihapus.');
    }

    /**
     * Tambah tanggal libur / kelas ditiadakan.
     */
    public function storeHoliday(Request $request)
    {
        $data = $request->validateWithBag('holiday', [
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique' => 'Tanggal tersebut sudah terdaftar sebagai hari libur.',
        ]);

        DB::transaction(function () use ($data) {
            $holiday = Holiday::create($data);
            ActivityLog::record('created', $holiday, 'Menambah hari libur '.$holiday->date->format('d M Y'));
        });
        ClassRoom::flushHolidayCache();

        return back()->with('success', 'Hari libur berhasil ditambahkan.');
    }

    /**
     * Hapus tanggal libur.
     */
    public function destroyHoliday(Holiday $holiday)
    {
        DB::transaction(function () use ($holiday) {
            ActivityLog::record('deleted', $holiday, 'Menghapus hari libur '.$holiday->date->format('d M Y'));
            $holiday->delete();
        });
        ClassRoom::flushHolidayCache();

        return back()->with('success', 'Hari libur berhasil dihapus.');
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
                    'cat' => $class->class_category, // nilai mentah untuk pencocokan level murid
                    'classId' => $class->id,
                    'code' => $class->class_code,
                    'availability' => $av['text'],
                    'available' => $available,
                    'occupancy' => $class->enrolledCount().' / '.$class->capacity,
                ],
            ];
        }

        // Replacement class (warna sesuai status).
        $statusColors = ['pending' => '#F59E0B', 'approved' => '#10B981', 'rejected' => '#EF4444'];
        $replacements = ReplacementRequest::with(['student', 'classRoom', 'originClass'])
            ->whereHas('student', fn ($s) => $s->paid())
            ->get();

        foreach ($replacements as $req) {
            $events[] = [
                'title' => 'Replacement: '.($req->student->name ?? '-'),
                'start' => $this->combineDateTime($req->replacement_date, $req->replacement_time),
                'color' => $statusColors[$req->request_status] ?? '#6B7280',
                'url' => route('schedules.edit', $req),
                'extendedProps' => [
                    'type' => 'Replacement Class',
                    'status' => ucfirst($req->request_status),
                    'originClass' => $req->originClass->class_name ?? '-',
                    'newClass' => $req->classRoom->class_name ?? '-',
                    'reason' => $req->reason ?: '-',
                ],
            ];
        }

        // Hari libur: tampil sebagai event seharian, berdiri sendiri walau tak ada jadwal kelas.
        // Dua representasi: tint latar seharian + chip berlabel (agar jelas & muncul di tampilan daftar).
        foreach (Holiday::orderBy('date')->get() as $holiday) {
            $date = $holiday->date->format('Y-m-d');
            $props = [
                'type' => 'Hari Libur',
                'reason' => $holiday->name ?: 'Kelas ditiadakan',
                'holiday' => true,
            ];
            // Oranye — sengaja dibedakan dari biru kelas reguler & amber replacement pending.
            // Tint latar seharian (FullCalendar merender background event dgn opacity rendah).
            $events[] = [
                'start' => $date,
                'allDay' => true,
                'display' => 'background',
                'color' => '#FB923C',
                'extendedProps' => $props,
            ];
            // Chip berlabel (bisa diklik, tampil juga di listMonth).
            $events[] = [
                'title' => '🏖️ Libur'.($holiday->name ? ': '.$holiday->name : ''),
                'start' => $date,
                'allDay' => true,
                'color' => '#EA580C',
                'extendedProps' => $props,
            ];
        }

        // Acara / agenda umum. Jam kosong = seharian.
        foreach (CalendarEvent::orderBy('date')->get() as $ev) {
            $allDay = $ev->isAllDay();
            $events[] = [
                'title' => $ev->title,
                'start' => $allDay ? $ev->date->format('Y-m-d') : $this->combineDateTime($ev->date, $ev->start_time),
                'end' => (! $allDay && $ev->end_time) ? $this->combineDateTime($ev->date, $ev->end_time) : null,
                'allDay' => $allDay,
                'color' => $ev->color ?: '#6366F1',
                'extendedProps' => [
                    'type' => 'Acara',
                    'note' => $ev->description ?: '-',
                ],
            ];
        }

        // Murid aktif & lunas untuk mode "Cari kelas pengganti" (filter slot per level murid).
        $students = Student::where('status', 'active')
            ->paid()
            ->orderBy('name')
            ->get(['id', 'name', 'student_id', 'class_type']);

        return view('schedules.calendar', ['events' => $events, 'students' => $students]);
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
        // Hanya murid lunas yang bisa diajukan replacement class.
        $students = $this->studentsWithActiveClass(Student::where('status', 'active')->paid());
        $classes = ClassRoom::with('tutor')->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
            ->orderBy('class_name')->get();

        return view('schedules.create', compact('students', 'classes'));
    }

    /**
     * Murid + kelas aktifnya, dipakai form untuk mengisi otomatis "kelas asal".
     */
    private function studentsWithActiveClass($query)
    {
        return $query->with(['classes' => fn ($q) => $q->wherePivot('status', 'active')])
            ->orderBy('name')
            ->get();
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
        // Murid lunas + murid yang sudah terlanjur dipilih di request ini.
        $students = $this->studentsWithActiveClass(
            Student::query()->where(fn ($q) => $q->paid()->orWhere('id', $schedule->student_id))
        );
        $classes = ClassRoom::with('tutor')->withCount(['students as enrolled_count' => fn ($q) => $q->where('student_class.status', 'active')])
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
     * Tipe kelas boleh berbeda — murid diizinkan pindah lintas tipe.
     *
     */
    private function validateReplacement(Request $request, ?ReplacementRequest $current = null): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id', new StudentPaymentSettled],
            // Kelas asal: jadwal yang ditinggalkan. Opsional karena data lama belum punya.
            'origin_class_id' => ['nullable', 'exists:classes,id', 'different:class_id'],
            'class_id' => [
                'required',
                'exists:classes,id',
                function ($attribute, $value, $fail) use ($current, $request) {
                    // Biarkan kelas yang sama saat edit walau statusnya kini berubah.
                    if ($current && (int) $value === (int) $current->class_id) {
                        return;
                    }

                    $class = ClassRoom::with('tutor')->find($value);
                    if (! $class) {
                        return;
                    }

                    if (! $class->isAvailable()) {
                        // Alasan spesifik sesuai kondisi slot.
                        $reason = match (true) {
                            $class->isClosed() => 'sudah ditutup admin',
                            $class->isPast() => 'jadwalnya sudah lewat',
                            $class->isHoliday() => 'jatuh pada hari libur',
                            ! $class->hasTutor() => 'belum ada tutor',
                            $class->isFull() => 'sudah penuh',
                            default => 'tidak tersedia',
                        };
                        $fail("Kelas \"{$class->class_name}\" {$reason} sehingga tidak bisa dijadikan slot pengganti. Silakan pilih slot lain yang tersedia.");
                    }

                    // Catatan: beda tipe kelas TIDAK ditolak — murid boleh pindah lintas
                    // tipe. Ketidakcocokan hanya ditandai di UI sebagai peringatan.
                },
            ],
            'replacement_date' => ['required', 'date'],
            'replacement_time' => ['required'],
            'reason' => ['nullable', 'string'],
        ], [
            // Pesan Indonesia agar admin langsung paham apa yang kurang.
            'student_id.required' => 'Murid belum dipilih.',
            'origin_class_id.different' => 'Kelas asal dan kelas baru tidak boleh sama.',
            'class_id.required' => 'Kelas tujuan belum dipilih. Jika daftar kelas kosong, berarti tidak ada slot yang tersedia saat ini.',
            'replacement_date.required' => 'Tanggal pengganti belum diisi.',
            'replacement_time.required' => 'Jam pengganti belum diisi.',
        ]);
    }
}
