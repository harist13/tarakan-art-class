<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ClassRoom;
use App\Models\ReplacementRequest;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    /**
     * Jatah sesi reguler seorang murid dalam sebulan — dasar rekap "2/4" di
     * daftar absen. Kelas berjalan mingguan, jadi sebulan berisi empat sesi.
     */
    public const SESSIONS_PER_MONTH = 4;

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
     * Daftar centang kehadiran untuk satu hari.
     *
     * Halaman ini tidak menanyakan apa pun selain tanggalnya: sesi mana yang
     * berjalan hari itu dan siapa yang seharusnya ada di dalamnya sepenuhnya
     * diturunkan dari jadwal — termasuk murid pengganti yang masuk dan murid yang
     * sesinya sudah dipindahkan. Admin tinggal mencentang siapa yang datang.
     */
    public function create(Request $request)
    {
        $date = $request->string('date')->toString() ?: now()->toDateString();
        $day = Carbon::parse($date);

        // Kelas yang kedatangan murid pengganti ikut dibuka sesinya walau hari itu
        // bukan hari rutinnya — muridnya tetap harus bisa dicentang.
        $replacementClassIds = ReplacementRequest::approved()
            ->whereDate('replacement_date', $date)
            ->pluck('class_id')
            ->unique();

        $sessions = ClassRoom::with('tutor')
            ->get()
            ->filter(fn (ClassRoom $class) => $class->occursOn($day) || $replacementClassIds->contains($class->id))
            ->sortBy(fn (ClassRoom $class) => [$class->timeLabel(), (string) $class->class_category])
            ->values()
            ->map(fn (ClassRoom $class) => $this->sessionRoster($class, $date));

        $sessionCounts = $this->monthlySessionCounts(
            $sessions->flatMap(fn (array $session) => $session['rows']->pluck('student.id'))->unique()->all(),
            $date
        );

        return view('attendances.create', [
            'date' => $date,
            'sessions' => $sessions,
            'sessionCounts' => $sessionCounts,
            'sessionQuota' => self::SESSIONS_PER_MONTH,
        ]);
    }

    /**
     * Siapa saja yang seharusnya hadir di satu sesi (kelas + tanggal).
     *
     * @return array{class: ClassRoom, rows: Collection, movedOut: Collection, suspended: Collection, existing: Collection}
     */
    private function sessionRoster(ClassRoom $class, string $date): array
    {
        // Murid yang sesinya justru pindah KE kelas ini pada tanggal ini.
        $incoming = ReplacementRequest::approved()
            ->with(['student.payments', 'originClass'])
            ->where('class_id', $class->id)
            ->whereDate('replacement_date', $date)
            ->get();

        // Murid yang meninggalkan sesi ini. Hanya request yang sudah disetujui yang
        // memindahkan murid; yang masih pending tetap diabsen di sini.
        //
        // Dua bentuk, keduanya lewat kelas ini sebagai kelas asal:
        //   - sesi yang dilewatkannya jatuh tepat pada tanggal ini, atau
        //   - ia masih punya sesi pengganti pada tanggal ini atau sesudahnya.
        //     Selama jadwal itu belum terlampaui, kehadirannya dicatat di sesi
        //     penggantinya — bukan di sesi reguler yang ia lewati.
        //
        // Bentuk kedua sengaja tidak bergantung pada missed_date: kolom itu sering
        // terisi otomatis ke tanggal lain, dan tanpa ini murid yang jelas sudah
        // punya jadwal pengganti tetap ikut terabsen di sesi reguler. Diukur
        // terhadap tanggal sesi yang dibuka, bukan terhadap hari ini, supaya
        // membuka rekap sesi lama tetap menjawab keadaan sesi itu.
        $outgoing = ReplacementRequest::approved()
            ->with('classRoom')
            ->where('origin_class_id', $class->id)
            ->where(function ($q) use ($date) {
                $q->whereDate('missed_date', $date)
                    ->orWhereDate('replacement_date', '>=', $date);
            })
            ->orderBy('replacement_date')
            ->get()
            ->groupBy('student_id');

        // Request untuk sesi ini yang BELUM disetujui. Muridnya masih diabsen di
        // sini, tapi kolom "Replacement?" harus sudah menandainya — kalau tidak,
        // admin mengajukan permintaan kedua untuk sesi yang sama tanpa sadar.
        $pendingOutgoing = ReplacementRequest::where('origin_class_id', $class->id)
            ->whereDate('missed_date', $date)
            ->where('request_status', '!=', 'approved')
            ->get()
            ->keyBy('student_id');

        $enrolled = $class->students()
            ->where('students.status', 'active')
            ->with('payments')
            ->orderBy('name')
            ->get();

        // Murid menunggak tetap bisa dicentang (hanya ditandai); yang keluar dari
        // daftar cuma yang sudah ditangguhkan.
        [$suspended, $attendable] = $enrolled->partition->isSuspended();

        [$movedOut, $attendable] = $attendable->partition(fn ($s) => $outgoing->has($s->id));
        $movedOut = $movedOut->map(fn ($s) => [
            'student' => $s,
            // Sesi pengganti terdekat; sisanya cukup dihitung, bukan dirinci.
            'replacement' => $outgoing[$s->id]->first(),
            'lainnya' => $outgoing[$s->id]->count() - 1,
        ]);

        // Dikunci per murid: replacement di kelas yang sama membuat satu murid
        // muncul dua kali — sebagai peserta tetap sekaligus sebagai pengganti.
        $byStudent = $attendable->mapWithKeys(fn ($s) => [$s->id => [
            'student' => $s,
            'replacement' => null,
            'pending' => $pendingOutgoing->get($s->id),
        ]]);

        foreach ($incoming as $req) {
            if (! $req->student || $req->student->isSuspended()) {
                continue;
            }
            $byStudent[$req->student_id] = [
                'student' => $byStudent[$req->student_id]['student'] ?? $req->student,
                'replacement' => $req,
                'pending' => $byStudent[$req->student_id]['pending'] ?? null,
            ];
        }

        // Sesi pengganti yang jatuh di kelas & tanggal ini mengembalikan muridnya
        // ke daftar — jangan sampai ia sekaligus dilaporkan meninggalkan sesi ini.
        $movedOut = $movedOut->reject(fn ($moved) => $byStudent->has($moved['student']->id))->values();

        // Replacement lain milik murid ini yang belum lewat — sesi yang
        // ditinggalkannya jatuh di tanggal lain, jadi tidak mengubah daftar hari
        // ini, tapi kolom Replacement tetap harus menandainya.
        $otherRequests = ReplacementRequest::with(['classRoom', 'originClass'])
            ->whereIn('student_id', $byStudent->keys())
            ->whereIn('request_status', ['pending', 'approved'])
            ->get()
            ->groupBy('student_id');

        $rows = $byStudent
            ->map(function ($row) use ($otherRequests) {
                $shown = collect([$row['replacement'], $row['pending']])->filter()->pluck('id');

                $row['others'] = $otherRequests->get($row['student']->id, collect())
                    ->reject(fn ($req) => $shown->contains($req->id) || $req->isPast())
                    ->sortBy(fn ($req) => $req->scheduledAt())
                    ->values();

                return $row;
            })
            ->sortBy(fn ($row) => $row['student']->name)
            ->values();

        return [
            'class' => $class,
            'rows' => $rows,
            'movedOut' => $movedOut,
            'suspended' => $suspended->values(),
            // Absensi yang sudah tercatat — dipakai menyalakan centangnya kembali.
            'existing' => Attendance::where('class_id', $class->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id'),
        ];
    }

    /**
     * Simpan satu sesi.
     *
     * `students` adalah seluruh murid yang tampil di sesi itu; `present` yang
     * tercentang, `permit` yang ditandai izin. Ketiganya dikirim bersama supaya
     * melepas centang benar-benar berarti "tidak hadir", bukan sekadar tidak
     * terkirim. Izin menang atas centang — keduanya tidak mungkin benar bersamaan.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'attendance_date' => ['required', 'date'],
            // Tanpa gerbang pembayaran — lihat catatan di App\Rules\StudentPaymentSettled.
            'students' => ['required', 'array'],
            'students.*' => ['required', 'exists:students,id'],
            'present' => ['nullable', 'array'],
            'present.*' => ['exists:students,id'],
            'permit' => ['nullable', 'array'],
            'permit.*' => ['exists:students,id'],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string'],
            // Penanda kolom "Replacement?", per id murid. Tidak ikut disimpan —
            // dipakai menahan penyimpanan bila jadwal penggantinya belum diatur.
            'replacement' => ['nullable', 'array'],
            'replacement.*' => ['nullable', Rule::in(['ya', 'tidak'])],
        ]);

        $this->assertReplacementsScheduled($data);

        $present = collect($data['present'] ?? [])->map(fn ($id) => (int) $id);
        $permit = collect($data['permit'] ?? [])->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($data, $present, $permit) {
            foreach ($data['students'] as $studentId) {
                $status = match (true) {
                    $permit->contains((int) $studentId) => 'permit',
                    $present->contains((int) $studentId) => 'present',
                    default => 'absent',
                };

                $nilai = [
                    'status' => $status,
                    'notes' => $data['notes'][$studentId] ?? null,
                    'recorded_by' => auth()->id(),
                ];

                // Catatan lama dicari dengan whereDate, bukan updateOrCreate:
                // tanggalnya tersimpan sebagai datetime, dan mencocokkan
                // "2026-09-02" apa adanya lalu meleset — sesi yang diabsen dua kali
                // akan berbaris ganda alih-alih diperbarui.
                $catatan = Attendance::where('student_id', $studentId)
                    ->where('class_id', $data['class_id'])
                    ->whereDate('attendance_date', $data['attendance_date'])
                    ->first();

                if ($catatan) {
                    $catatan->update($nilai);

                    continue;
                }

                Attendance::create($nilai + [
                    'student_id' => $studentId,
                    'class_id' => $data['class_id'],
                    'attendance_date' => $data['attendance_date'],
                ]);
            }

            ActivityLog::record('created', null, 'Input absensi kelas');
        });

        $class = ClassRoom::find($data['class_id']);

        // Kembali ke hari yang sama: satu hari biasanya berisi beberapa sesi, dan
        // admin belum tentu selesai setelah menyimpan yang pertama.
        return redirect()->route('attendances.create', ['date' => $data['attendance_date']])
            ->with('success', 'Absensi '.($class->class_category ?? 'kelas').' tersimpan — '
                .$present->count().' dari '.count($data['students']).' murid hadir.');
    }

    /**
     * Tahan penyimpanan selama masih ada murid bertanda "Replacement: Ya" yang
     * sesi penggantinya belum dijadwalkan.
     *
     * Tandanya sendiri tidak disimpan di absensi — jadwal pengganti hidup di
     * ReplacementRequest. Kalau absensi tetap tersimpan, tanda itu lenyap tanpa
     * jejak dan sesi penggantinya tidak pernah benar-benar diatur.
     *
     * Sudah terjadwal bila murid meninggalkan sesi ini (origin + missed_date) atau
     * justru datang ke sini sebagai pengganti (class + replacement_date) — status
     * request-nya tidak dilihat: yang pending pun tanggalnya sudah ada.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertReplacementsScheduled(array $data): void
    {
        $flagged = collect($data['replacement'] ?? [])
            ->filter(fn ($pilihan) => $pilihan === 'ya')
            ->keys()
            ->map(fn ($id) => (int) $id)
            // Hanya murid yang benar-benar ada di sesi yang sedang disimpan.
            ->intersect(collect($data['students'])->map(fn ($id) => (int) $id));

        if ($flagged->isEmpty()) {
            return;
        }

        $scheduled = ReplacementRequest::whereIn('student_id', $flagged)
            ->where(function ($q) use ($data) {
                $q->where(fn ($sub) => $sub->where('origin_class_id', $data['class_id'])
                    ->whereDate('missed_date', $data['attendance_date']))
                    ->orWhere(fn ($sub) => $sub->where('class_id', $data['class_id'])
                        ->whereDate('replacement_date', $data['attendance_date']));
            })
            ->pluck('student_id');

        $missing = $flagged->diff($scheduled);

        if ($missing->isEmpty()) {
            return;
        }

        $names = Student::whereIn('id', $missing)->orderBy('name')->pluck('name')->implode(', ');

        throw ValidationException::withMessages([
            'replacement' => "Jadwal pengganti {$names} belum diatur. Klik tombol pensil di kolom Replacement untuk menentukan tanggalnya dulu, baru simpan absensinya.",
        ]);
    }

    /**
     * Berapa sesi yang sudah dihadiri tiap murid sepanjang bulan tanggal ini.
     *
     * Dihitung lintas kelas, bukan hanya kelas yang sedang diabsen: sesi pengganti
     * dijalani di kelas lain, tapi tetap memakai jatah bulan yang sama. Hanya
     * status "hadir" yang dihitung — izin & absen bukan sesi yang terpakai.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, int>
     */
    private function monthlySessionCounts(array $studentIds, string $date)
    {
        if ($studentIds === []) {
            return collect();
        }

        $month = Carbon::parse($date);

        return Attendance::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', 'present')
            ->whereBetween('attendance_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->groupBy('student_id')
            ->selectRaw('student_id, COUNT(*) as total')
            ->pluck('total', 'student_id');
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return back()->with('success', 'Data absensi berhasil dihapus.');
    }
}
