<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" class="form-select" required>
            <option value="">— Pilih murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $report->student_id ?? ($prefillStudentId ?? '')) == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
        @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle me-1"></i>Semua murid aktif bisa dipilih, termasuk yang menunggak — raportnya tetap boleh disusun.
            Yang tertahan saat menunggak adalah orang tua membukanya lewat credential key.
            @if($students->isEmpty())
                <span class="text-danger d-block">Belum ada murid aktif — tambahkan lewat <a href="{{ route('students.index') }}">Data murid &amp; wali</a>.</span>
            @endif
        </small>
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode mulai</label>
        <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" value="{{ old('period_start', isset($report) ? $report->period_start->format('Y-m-d') : ($defaultStart ?? '')) }}" required>
        @error('period_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label">Periode selesai</label>
        <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" value="{{ old('period_end', isset($report) ? $report->period_end->format('Y-m-d') : ($defaultEnd ?? '')) }}" required>
        @error('period_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1">Harus di bulan yang sama dengan tanggal mulai.</small>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan aktivitas / perkembangan</label>
        <textarea name="activity_notes" class="form-control" rows="4" required>{{ old('activity_notes', $report->activity_notes ?? '') }}</textarea>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Catatan tutor (opsional)</label>
        <textarea name="tutor_notes" class="form-control" rows="4">{{ old('tutor_notes', $report->tutor_notes ?? '') }}</textarea>
    </div>

    {{-- Foto progres: perbandingan awal vs akhir periode, termasuk karya yang
         belum selesai. Beda dengan Galeri Karya, yang khusus karya jadi. --}}
    <div class="col-12 mb-3">
        <label class="form-label mb-1">Foto progres <span class="text-muted">(opsional)</span></label>
        <small class="text-muted d-block mb-2">
            <i class="bi bi-info-circle me-1"></i>Tunjukkan perkembangan dari awal ke akhir bulan, termasuk karya yang belum selesai
            (mis. minggu pertama blending kepala, minggu terakhir blending badan). Karya yang sudah jadi diunggah di
            <a href="{{ route('artworks.index') }}">Galeri Karya</a>.
        </small>
        <div class="row g-3">
            @foreach(\App\Models\StudentReport::PROGRESS_SLOTS as $slot => $label)
                @php
                    $kolom = "progress_{$slot}_photo";
                    $fotoLama = $report?->{$kolom};
                @endphp
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">
                        <div class="fw-semibold mb-2">{{ $label }}</div>
                        @if($fotoLama)
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <a href="{{ asset('storage/'.$fotoLama) }}" target="_blank" rel="noopener">
                                    <img src="{{ asset('storage/'.$fotoLama) }}" alt="Foto progres {{ strtolower($label) }}" class="rounded border" style="width:96px; height:96px; object-fit:cover;">
                                </a>
                                <div class="form-check small">
                                    <input class="form-check-input" type="checkbox" name="remove_{{ $kolom }}" value="1" id="remove_{{ $kolom }}" @checked(old("remove_{$kolom}"))>
                                    <label class="form-check-label" for="remove_{{ $kolom }}">Hapus foto ini</label>
                                    <div class="text-muted">Atau pilih berkas baru untuk menggantinya.</div>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="{{ $kolom }}" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm mb-2 @error($kolom) is-invalid @enderror">
                        @error($kolom)<div class="invalid-feedback mb-2">{{ $message }}</div>@enderror
                        <input type="text" name="progress_{{ $slot }}_caption" maxlength="255" class="form-control form-control-sm @error("progress_{$slot}_caption") is-invalid @enderror"
                               placeholder="Keterangan, mis. {{ $slot === 'first' ? 'Blending kepala' : 'Blending badan' }}"
                               value="{{ old("progress_{$slot}_caption", $report?->{"progress_{$slot}_caption"}) }}">
                        @error("progress_{$slot}_caption")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endforeach
        </div>
        <small class="text-muted d-block mt-1">JPG, PNG, atau WEBP, maksimal 4MB per foto.</small>
    </div>
</div>
