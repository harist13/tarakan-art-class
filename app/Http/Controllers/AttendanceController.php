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

            // Murid yang meninggalkan sesi ini. Hanya request yang sudah disetujui
            // yang memindahkan murid; yang masih pending tetap diabsen di sini.
            //
            // Dua bentuk, keduanya lewat kelas ini sebagai kelas asal:
            //   - sesi yang dilewatkannya jatuh tepat pada tanggal ini, atau
            //   - ia masih punya sesi pengganti pada tanggal ini atau sesudahnya.
            //     Selama jadwal itu belum terlampaui, kehadirannya dicatat di sesi
            //     penggantinya — bukan di sesi reguler yang ia lewati.
            //
            // Bentuk kedua sengaja tidak bergantung pada missed_date: kolom itu
            // sering terisi otomatis ke tanggal lain, dan tanpa ini murid yang
            // jelas-jelas sudah punya jadwal pengganti tetap ikut terabsen di sesi
            // reguler. Diukur terhadap tanggal sesi yang sedang dibuka, bukan
            // terhadap hari ini, supaya rekap sesi lama tetap menjawab keadaan
            // sesi itu.
            $outgoing = ReplacementRequest::approved()
                ->with('classRoom')
                ->where('origin_class_id', $selectedClass->id)
                ->where(function ($q) use ($date) {
                    $q->whereDate('missed_date', $date)
                        ->orWhereDate('replacement_date', '>=', $date);
                })
                ->orderBy('replacement_date')
                ->get()
                ->groupBy('student_id');

            // Request untuk sesi ini yang BELUM disetujui. Muridnya masih diabsen
            // di sini (hanya yang approved yang benar-benar berpindah), tapi kolom
            // "Replacement?" harus sudah menandainya — kalau tidak, admin mengajukan
            // permintaan kedua untuk sesi yang sama tanpa sadar.
            $pendingOutgoing = ReplacementRequest::where('origin_class_id', $selectedClass->id)
                ->whereDate('missed_date', $date)
                ->where('request_status', '!=', 'approved')
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

            // Sesi pengganti yang jatuh di kelas & tanggal ini mengembalikan
            // muridnya ke daftar absen — jangan sampai ia sekaligus dilaporkan
            // "memindahkan sesi ini".
            $movedOut = $movedOut->reject(fn ($moved) => $byStudent->has($moved['student']->id))->values();

            // Replacement lain milik murid-murid ini yang belum lewat — sesi yang
            // ditinggalkannya jatuh di tanggal lain, jadi tidak mengubah daftar
            // hari ini. Tetap ditampilkan: tanpa itu admin tak punya cara tahu
            // anak ini sudah punya jadwal pengganti, dan missed_date yang keliru
            // (mis. terisi otomatis ke sesi lain) tidak pernah ketahuan.
            // Yang ditolak tidak ikut: itu bukan jadwal, hanya riwayat.
            $otherRequests = ReplacementRequest::with(['classRoom', 'originClass'])
                ->whereIn('student_id', $byStudent->keys())
                ->whereIn('request_status', ['pending', 'approved'])
                ->get()
                ->groupBy('student_id');

            $rows = $byStudent
                ->map(function ($row) use ($otherRequests) {
                    // Yang sudah punya keterangannya sendiri di baris ini tidak
                    // diulang sebagai "replacement lain".
                    $shown = collect([$row['replacement'], $row['pending']])->filter()->pluck('id');

                    $row['others'] = $otherRequests->get($row['student']->id, collect())
                        ->reject(fn ($req) => $shown->contains($req->id) || $req->isPast())
                        ->sortBy(fn ($req) => $req->scheduledAt())
                        ->values();

                    return $row;
                })
                ->sortBy(fn ($row) => $row['student']->name)
                ->values();
        }

        // Absensi yang sudah tercatat untuk sesi ini — dipakai mengisi ulang form
        // supaya membukanya kembali tidak menimpa catatan lama dengan nilai bawaan.
        $existing = $selectedClass
            ? Attendance::where('class_id', $selectedClass->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('student_id')
            : collect();

        // Rekap "sudah masuk berapa kali bulan ini" per murid di daftar ini.
        $sessionCounts = $this->monthlySessionCounts($rows->pluck('student.id')->all(), $date);
        $sessionQuota = self::SESSIONS_PER_MONTH;

        return view('attendances.create', compact(
            'classes', 'selectedClass', 'date', 'rows', 'suspendedStudents',
            'movedOut', 'existing', 'occursOnDate', 'sessionCounts', 'sessionQuota'
        ));
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
            // Penanda kolom "Replacement?". Tidak ikut disimpan — dipakai untuk
            // menahan penyimpanan bila jadwal penggantinya belum diatur.
            'records.*.replacement' => ['nullable', Rule::in(['ya', 'tidak'])],
        ]);

        $this->assertReplacementsScheduled($data);

        DB::transaction(function () use ($data) {
            foreach ($data['records'] as $record) {
                // Catatan lama dicari dengan whereDate, bukan updateOrCreate:
                // tanggalnya bisa tersimpan sebagai datetime, dan mencocokkan
                // "2026-09-02" apa adanya lalu meleset — sesi yang diabsen dua
                // kali akan berbaris ganda alih-alih diperbarui.
                $catatan = Attendance::where('student_id', $record['student_id'])
                    ->where('class_id', $data['class_id'])
                    ->whereDate('attendance_date', $data['attendance_date'])
                    ->first();

                $nilai = [
                    'status' => $record['status'],
                    'notes' => $record['notes'] ?? null,
                    'recorded_by' => auth()->id(),
                ];

                if ($catatan) {
                    $catatan->update($nilai);

                    continue;
                }

                Attendance::create($nilai + [
                    'student_id' => $record['student_id'],
                    'class_id' => $data['class_id'],
                    'attendance_date' => $data['attendance_date'],
                ]);
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

    /**
     * Tahan penyimpanan selama masih ada murid bertanda "Replacement: Ya" yang
     * sesi penggantinya belum dijadwalkan.
     *
     * Tandanya sendiri tidak disimpan di absensi — jadwal pengganti hidup di
     * ReplacementRequest. Kalau absensi tetap tersimpan, tanda itu lenyap tanpa
     * jejak dan sesi penggantinya tidak pernah benar-benar diatur. Pemeriksaan
     * inilah yang membuat "Ya" berarti sesuatu.
     *
     * Sudah terjadwal bila murid meninggalkan sesi ini (origin + missed_date) atau
     * justru datang ke sini sebagai pengganti (class + replacement_date) — status
     * request-nya tidak dilihat: yang pending pun tanggalnya sudah ada, dan itu
     * yang sedang diminta di sini.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertReplacementsScheduled(array $data): void
    {
        $flagged = collect($data['records'])
            ->filter(fn ($record) => ($record['replacement'] ?? 'tidak') === 'ya')
            ->pluck('student_id')
            ->unique();

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

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();

        return back()->with('success', 'Data absensi berhasil dihapus.');
    }
}
