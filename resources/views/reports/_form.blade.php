<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $report->student_id ?? '') == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode Mulai</label>
        <input type="date" name="period_start" class="form-control" value="{{ old('period_start', isset($report) ? $report->period_start->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode Selesai</label>
        <input type="date" name="period_end" class="form-control" value="{{ old('period_end', isset($report) ? $report->period_end->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Nilai Pencapaian (0-100)</label>
        <input type="number" min="0" max="100" name="achievement_score" class="form-control" value="{{ old('achievement_score', $report->achievement_score ?? '') }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan Aktivitas / Perkembangan</label>
        <textarea name="activity_notes" class="form-control" rows="4" required>{{ old('activity_notes', $report->activity_notes ?? '') }}</textarea>
    </div>
    <div class="col-md-8 mb-3">
        <label class="form-label">Catatan Tutor (opsional)</label>
        <textarea name="tutor_notes" class="form-control" rows="4">{{ old('tutor_notes', $report->tutor_notes ?? '') }}</textarea>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Foto Siswa (pas foto 4&times;6)</label>
        <div class="d-flex gap-3 align-items-start">
            <img id="photoPreview"
                 src="{{ isset($report) && $report->photoUrl() ? $report->photoUrl() : '' }}"
                 alt="Preview 4x6"
                 class="border rounded {{ isset($report) && $report->photoUrl() ? '' : 'd-none' }}"
                 style="width:4cm; height:6cm; object-fit:cover; background:#f1f5f9;">
            <div class="flex-grow-1">
                <input type="file" name="photo" id="photoInput" class="form-control" accept="image/jpeg,image/png">
                <small class="text-muted d-block mt-1">Format JPG/PNG, maks 2MB. Rasio 4:6 (potret).</small>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const input = document.getElementById('photoInput');
    const preview = document.getElementById('photoPreview');
    input?.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');
        }
    });
</script>
@endpush
