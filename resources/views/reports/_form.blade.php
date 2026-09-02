<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $report->student_id ?? '') == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
        @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle me-1"></i>Semua murid aktif bisa dipilih, termasuk yang menunggak — raportnya tetap boleh disusun.
            Yang tertahan saat menunggak adalah orang tua membukanya lewat credential key.
            @if($students->isEmpty())
                <span class="text-danger d-block">Belum ada murid aktif — tambahkan lewat <a href="{{ route('students.index') }}">Data Murid &amp; Wali</a>.</span>
            @endif
        </small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode Mulai</label>
        <input type="date" name="period_start" class="form-control" value="{{ old('period_start', isset($report) ? $report->period_start->format('Y-m-d') : ($defaultStart ?? '')) }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode Selesai</label>
        <input type="date" name="period_end" class="form-control" value="{{ old('period_end', isset($report) ? $report->period_end->format('Y-m-d') : ($defaultEnd ?? '')) }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan Aktivitas / Perkembangan</label>
        <textarea name="activity_notes" class="form-control" rows="4" required>{{ old('activity_notes', $report->activity_notes ?? '') }}</textarea>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan Tutor (opsional)</label>
        <textarea name="tutor_notes" class="form-control" rows="4">{{ old('tutor_notes', $report->tutor_notes ?? '') }}</textarea>
    </div>
</div>
