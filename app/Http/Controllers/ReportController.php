<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\StudentReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();

        $reports = StudentReport::query()
            ->with(['student', 'creator'])
            ->when($search, fn ($q) => $q->where('credential_key', 'like', "%{$search}%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('reports.index', compact('reports', 'search'));
    }

    public function create()
    {
        $students = Student::where('status', 'active')->orderBy('name')->get();

        return view('reports.create', compact('students'));
    }

    public function store(Request $request)
    {
        $data = $this->reportData($this->validateData($request));
        $data['created_by'] = auth()->id();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('report-photos', 'public');
        }

        try {
            $report = DB::transaction(function () use ($data) {
                $report = StudentReport::create($data);
                ActivityLog::record('created', $report, "Membuat raport {$report->credential_key}");

                return $report;
            });
        } catch (\Throwable $e) {
            // Bersihkan foto yang terlanjur diupload bila transaksi gagal.
            if (! empty($data['photo_path'])) {
                Storage::disk('public')->delete($data['photo_path']);
            }
            throw $e;
        }

        return redirect()->route('reports.index')
            ->with('success', "Raport berhasil dibuat. Credential key: {$report->credential_key}");
    }

    public function show(StudentReport $report)
    {
        $report->load(['student', 'creator']);

        return view('reports.show', compact('report'));
    }

    public function edit(StudentReport $report)
    {
        $students = Student::orderBy('name')->get();

        return view('reports.edit', compact('report', 'students'));
    }

    public function update(Request $request, StudentReport $report)
    {
        $data = $this->reportData($this->validateData($request));

        $oldPhoto = $report->photo_path;
        $newPhoto = null;
        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('report-photos', 'public');
            $data['photo_path'] = $newPhoto;
        }

        try {
            DB::transaction(function () use ($report, $data) {
                $report->update($data);
                ActivityLog::record('updated', $report, "Memperbarui raport {$report->credential_key}");
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $e;
        }

        // Hapus foto lama hanya setelah commit sukses & memang diganti.
        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()->route('reports.index')->with('success', 'Raport berhasil diperbarui.');
    }

    public function destroy(StudentReport $report)
    {
        $photo = $report->photo_path;

        DB::transaction(function () use ($report) {
            ActivityLog::record('deleted', $report, "Menghapus raport {$report->credential_key}");
            $report->delete();
        });

        // Hapus file foto hanya setelah commit sukses.
        if ($photo) {
            Storage::disk('public')->delete($photo);
        }

        return redirect()->route('reports.index')->with('success', 'Raport berhasil dihapus.');
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

        return view('reports.guest', compact('report'));
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'activity_notes' => ['required', 'string'],
            'achievement_score' => ['required', 'integer', 'min:0', 'max:100'],
            'tutor_notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:2048'], // foto pas 4x6, maks 2MB
        ]);
    }

    /**
     * Buang key 'photo' dari data yang akan disimpan ke DB (bukan kolom).
     */
    private function reportData(array $data): array
    {
        unset($data['photo']);

        return $data;
    }
}
