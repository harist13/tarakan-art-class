<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->with(['student.payments', 'classRoom'])
            // Absensi tidak digerbang tagihan: kehadiran yang sudah terjadi harus
            // tetap terlihat & terekap. Murid menunggak hanya ditandai di kolomnya.
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->when($date, fn ($q) => $q->whereDate('attendance_date', $date))
            ->when($search, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")
                ->orWhere('student_id', 'like', "%{$search}%")))
            ->orderByDesc('attendance_date')
            ->paginate(15)
            ->withQueryString();

        $classes = ClassRoom::orderBy('class_category')->get();

        return view('attendances.index', compact('attendances', 'classes', 'classId', 'date', 'search'));
    }

    /**
     * Daftar absen satu sesi: satu kelas pada satu tanggal.
     *
     * Tanggalnya ikut menentukan isi daftar, bukan sekadar label yang disimpan —
     * replacement class memindahkan murid antar sesi, jadi "siapa yang hadir di
     * kelas ini" hanya bisa dijawab bersama tanggalnya.
     */
    public function create(Request $request)
    {
        $classes = ClassRoom::orderBy('class_category')->get();
        $date = $request->string('date')->toString() ?: now()->toDateString();
        $selectedClass = null;
        $rows = collect();
        $suspendedStudents = collect();
        $movedOut = collect();
        $occursOnDate = true;

        if ($request->filled('class_id')) {
            $selectedClass = ClassRoom::find($request->integer('class_id'));
        }

        if ($selectedClass) {
            $occursOnDate = $selectedClass->occursOn(Carbon::parse($date));

            // Murid yang sesinya justru pindah KE kelas ini pada tanggal ini.
            $incoming = ReplacementRequest::approved()
                ->with(['student.payments', 'originClass'])
                ->where('class_id', $selectedClass->id)
                ->whereDate('replacement_date', $date)
                ->get();

            // Murid yang meninggalkan sesi ini — sesi yang dilewatkannya jatuh
            // tepat pada tanggal ini. Request lama tanpa missed_date tidak ikut
            // tersaring: lebih baik muridnya tetap muncul daripada hilang diam-diam.
            $outgoing = ReplacementRequest::approved()
                ->with('classRoom')
                ->where('origin_class_id', $selectedClass->id)
                ->whereDate('missed_date', $date)
                ->get()
                ->keyBy('student_id');

            $enrolled = $selectedClass->students()
                ->where('students.status', 'active')
                ->with('payments')
                ->orderBy('name')
                ->get();

            // Semua murid aktif di kelas ini bisa diabsen, termasuk yang
            // menunggak (ditandai di daftar). Yang keluar dari daftar hanya
            // murid yang sudah ditangguhkan — mereka memang tidak ikut kelas
            // lagi sampai tunggakannya dilunasi.
            [$suspendedStudents, $attendable] = $enrolled->partition->isSuspended();

            // Sesi yang sudah dipindahkan tidak diabsen di sini: mencatatnya
            // sebagai "absen" akan merusak rekap kehadiran murid yang justru
            // sudah mengurus penggantinya.
            [$movedOut, $attendable] = $attendable->partition(fn ($s) => $outgoing->has($s->id));
            $movedOut = $movedOut->map(fn ($s) => ['student' => $s, 'replacement' => $outgoing[$s->id]]);

            // Dikunci per murid: replacement di kelas yang sama membuat satu murid
            // muncul dua kali — sebagai peserta tetap sekaligus sebagai pengganti.
            $byStudent = $attendable->mapWithKeys(fn ($s) => [$s->id => ['student' => $s, 'replacement' => null]]);

            foreach ($incoming as $req) {
                if (! $req->student || $req->student->isSuspended()) {
                    continue;
                }
                $byStudent[$req->student_id] = [
                    'student' => $byStudent[$req->student_id]['student'] ?? $req->student,
                    'replacement' => $req,
                ];
            }

            $rows = $byStudent->sortBy(fn ($row) => $row['student']->name)->values();
        }

        // Absensi yang sudah tercatat untuk sesi ini — dipakai mengisi ulang form
        // supaya membukanya kembali tidak menimpa catatan lama dengan nilai bawaan.
        $existing = $selectedClass
            ? Attendance::where('class_id', $selectedClass->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id')
            : collect();

        return view('attendances.create', compact(
            'classes', 'selectedClass', 'date', 'rows', 'suspendedStudents',
            'movedOut', 'existing', 'occursOnDate'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'attendance_date' => ['required', 'date'],
            'records' => ['required', 'array'],
            // Tanpa gerbang pembayaran — lihat catatan di App\Rules\StudentPaymentSettled.
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

        // Diarahkan ke rekap yang sudah tersaring ke sesi yang baru disimpan,
        // supaya admin langsung melihat hasil catatannya — bukan daftar penuh.
        return redirect()->route('attendances.index', [
            'class_id' => $data['class_id'],
            'date' => $data['attendance_date'],
        ])->with('success', 'Absensi berhasil disimpan.');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return back()->with('success', 'Data absensi berhasil dihapus.');
    }
}
