<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Artwork;
use App\Models\Student;
use App\Models\StudentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->string('month')->toString();
        $search = $request->string('search')->toString();

        // Jumlah raport yang tertahan dari orang tua karena muridnya menunggak.
        $withheldCount = StudentReport::whereHas('student', fn ($s) => $s->inArrears())->count();

        // Mode detail bulan: tampilkan semua raport di bulan itu.
        if ($month !== '' && preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $reports = StudentReport::query()
                ->with(['student', 'creator'])
                ->whereYear('period_start', $m[1])
                ->whereMonth('period_start', $m[2])
                ->when($search, fn ($q) => $q->where('credential_key', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")))
                ->orderBy(
                    Student::select('name')
                        ->whereColumn('students.id', 'student_reports.student_id')
                        ->limit(1)
                )
                ->get();

            // Jumlah karya tiap murid di bulan ini, untuk badge pada tombol folder.
            // Satu query terpisah, bukan withCount: relasinya perlu disaring per
            // bulan, dan angkanya cuma dipakai sebagai penanda di tombol.
            $artworkCounts = Artwork::query()
                ->inMonth($month)
                ->whereIn('student_id', $reports->pluck('student_id'))
                ->selectRaw('student_id, COUNT(*) as total')
                ->groupBy('student_id')
                ->pluck('total', 'student_id');

            return view('reports.index', compact('reports', 'month', 'search', 'withheldCount', 'artworkCounts'));
        }

        // Mode default: daftar bulan beserta jumlah raport.
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', period_start)"
            : "DATE_FORMAT(period_start, '%Y-%m')";

        $months = StudentReport::query()
            ->selectRaw("{$monthExpr} as month, COUNT(*) as total")
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        return view('reports.index', compact('months', 'search', 'withheldCount'));
    }

    public function create(Request $request)
    {
        // Murid yang dituju bila form dibuka dari folder karya — lihat tombol
        // "Buat Raport" di artworks/folder.
        $prefillStudentId = $request->integer('student_id') ?: null;

        // Raport boleh disusun untuk semua murid yang masih ikut kelas — menahan
        // penulisannya hanya membuat pekerjaan tutor menumpuk. Yang tertahan saat
        // menunggak adalah akses orang tua ke hasilnya.
        //
        // Murid prefill ikut dimuat walau statusnya sudah tidak attendable: tombol
        // itu menjanjikan murid tersebut terpilih, jangan sampai jatuh ke kosong.
        $students = Student::query()
            ->where(fn ($q) => $q->attendable()
                ->when($prefillStudentId, fn ($s) => $s->orWhere('id', $prefillStudentId)))
            ->orderBy('name')
            ->get();

        // Pre-fill periode berdasarkan folder bulan yang sedang dibuka.
        $month = $request->string('month')->toString();
        $defaultStart = '';
        $defaultEnd = '';
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $dt = \Carbon\Carbon::createFromFormat('Y-m', $month);
            $defaultStart = $dt->copy()->startOfMonth()->format('Y-m-d');
            $defaultEnd = $dt->copy()->endOfMonth()->format('Y-m-d');
        }

        return view('reports.create', compact('students', 'defaultStart', 'defaultEnd', 'prefillStudentId'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();

        $report = DB::transaction(function () use ($data) {
            $report = StudentReport::create($data);
            ActivityLog::record('created', $report, "Membuat raport {$report->credential_key}");

            return $report;
        });

        return redirect()->route('reports.index', ['month' => $report->period_start->format('Y-m')])
            ->with('success', "Raport berhasil dibuat. Credential key: {$report->credential_key}");
    }

    public function show(StudentReport $report)
    {
        $report->load(['student', 'creator']);

        return view('reports.show', [
            'report' => $report,
            'artworks' => $report->artworkQuery()->get(),
        ]);
    }

    public function edit(StudentReport $report)
    {
        // Murid yang masih ikut kelas + murid yang sudah terlanjur dipilih di
        // raport ini, agar pilihan lamanya tidak hilang dari dropdown saat mengedit.
        $students = Student::query()
            ->where(fn ($q) => $q->attendable()->orWhere('id', $report->student_id))
            ->orderBy('name')
            ->get();

        return view('reports.edit', compact('report', 'students'));
    }

    public function update(Request $request, StudentReport $report)
    {
        $data = $this->validateData($request, $report->id);

        DB::transaction(function () use ($report, $data) {
            $report->update($data);
            ActivityLog::record('updated', $report, "Memperbarui raport {$report->credential_key}");
        });

        return redirect()->route('reports.index', ['month' => $report->period_start->format('Y-m')])->with('success', 'Raport berhasil diperbarui.');
    }

    public function destroy(StudentReport $report)
    {
        DB::transaction(function () use ($report) {
            ActivityLog::record('deleted', $report, "Menghapus raport {$report->credential_key}");
            $report->delete();
        });

        return redirect()->route('reports.index', ['month' => $report->period_start->format('Y-m')])->with('success', 'Raport berhasil dihapus.');
    }

    // ─── Guest Report Access (F9) — tanpa login ──────────────────

    public function guestForm()
    {
        return view('reports.guest');
    }

    public function guestShow(Request $request)
    {
        $request->validate([
            'credential_key' => ['required', 'string'],
        ]);

        $report = StudentReport::with('student')
            ->where('credential_key', $request->string('credential_key')->toString())
            ->first();

        if (! $report) {
            return back()->withErrors(['credential_key' => 'Credential key tidak ditemukan.'])->onlyInput('credential_key');
        }

        // Raport ditahan selama ada tagihan yang lewat jatuh tempo. Invoice yang
        // baru terbit & belum jatuh tempo tidak menahan apa pun.
        if (! $report->student || $report->student->hasArrears()) {
            return back()->withErrors([
                'credential_key' => 'Raport belum dapat dibuka karena ada tagihan yang lewat jatuh tempo. Silakan hubungi admin.',
            ])->onlyInput('credential_key');
        }

        return view('reports.guest', [
            'report' => $report,
            'artworks' => $report->artworkQuery()->get(),
        ]);
    }

    private function validateData(Request $request, ?int $excludeId = null): array
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'activity_notes' => ['required', 'string'],
            'tutor_notes' => ['nullable', 'string'],
        ]);

        // Cek duplikat: murid yang sama di bulan periode yang sama.
        $periodDate = \Carbon\Carbon::parse($data['period_start']);
        $exists = StudentReport::query()
            ->where('student_id', $data['student_id'])
            ->whereYear('period_start', $periodDate->year)
            ->whereMonth('period_start', $periodDate->month)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'student_id' => 'Murid ini sudah memiliki raport di bulan ' . $periodDate->translatedFormat('F Y') . '.',
            ]);
        }

        return $data;
    }
}
