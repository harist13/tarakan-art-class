<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" id="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" data-category="{{ $student->class_type }}" @selected(old('student_id', $request->student_id ?? request('student_id', '')) == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kelas Tujuan Replacement</label>
        @php $availableCount = $classes->filter->isAvailable()->count(); @endphp
        <select name="class_id" id="class_id" class="form-select" required>
            <option value="">— Pilih Kelas —</option>
            @foreach($classes as $class)
                @php $selected = old('class_id', $request->class_id ?? request('class_id', '')) == $class->id; @endphp
                {{-- Hanya tampilkan slot yang tersedia; kelas yang sedang dipilih (saat edit) tetap muncul. --}}
                @if($class->isAvailable() || $selected)
                    <option value="{{ $class->id }}" data-category="{{ $class->class_category }}" @selected($selected)>
                        {{ $class->class_name }} ({{ ucfirst($class->class_category) }}) — {{ $class->availability()['text'] }}
                    </option>
                @endif
            @endforeach
        </select>
        <small class="text-muted d-block mt-1" id="classHint">
            @if($availableCount === 0)
                <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak ada slot yang tersedia saat ini.</span>
            @else
                <i class="bi bi-funnel me-1"></i>Pilih murid dulu untuk menyaring slot yang cocok dengan tipe kelasnya.
            @endif
        </small>
    </div>

    {{-- Peringatan + panduan saat tak ada slot cocok; submit dinonaktifkan agar tak gagal di server. --}}
    <div class="col-12 mb-3 d-none" id="noSlotAlert">
        <div class="alert alert-warning mb-0">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Tidak ada slot pengganti untuk murid ini</div>
            <p class="small mb-2" id="noSlotReason">Semua slot dengan tipe kelas yang sama sedang penuh, ditutup, sudah lewat jadwalnya, jatuh di hari libur, atau tutornya belum tersedia.</p>
            <div class="small mb-0">Yang bisa dilakukan:
                <ul class="mb-0 ps-3">
                    <li>Buka kembali slot yang ditutup lewat <a href="{{ route('schedules.index') }}" class="alert-link">Pengaturan Jadwal &amp; Slot</a>.</li>
                    <li>Tambah kelas baru dengan jadwal mendatang di <a href="{{ route('classes.index') }}" class="alert-link">Manajemen Kelas</a>.</li>
                    <li>Pastikan tutor kelas tersebut berstatus aktif.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Pengganti</label>
        <input type="date" name="replacement_date" class="form-control" value="{{ old('replacement_date', isset($request) ? $request->replacement_date->format('Y-m-d') : request('replacement_date', '')) }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jam Pengganti</label>
        <input type="time" name="replacement_time" class="form-control" value="{{ old('replacement_time', isset($request) ? \Illuminate\Support\Str::of($request->replacement_time)->substr(0,5) : request('replacement_time', '')) }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Alasan</label>
        <textarea name="reason" class="form-control" rows="2">{{ old('reason', $request->reason ?? '') }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const studentSel = document.getElementById('student_id');
    const classSel = document.getElementById('class_id');
    const hint = document.getElementById('classHint');
    const noSlotAlert = document.getElementById('noSlotAlert');
    if (!studentSel || !classSel) return;

    const form = classSel.form;
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

    // Simpan daftar kelas asli; dropdown di-render ulang dari sini saat menyaring.
    const allClasses = Array.from(classSel.options)
        .filter(function (o) { return o.value; })
        .map(function (o) { return { value: o.value, text: o.textContent.trim(), cat: o.dataset.category }; });

    // Saring slot: hanya tampilkan kelas yang kategorinya cocok dengan tipe kelas murid.
    // Select di aplikasi ini di-upgrade jadi Tom Select, jadi pakai API-nya bila tersedia.
    function filterClasses() {
        const opt = studentSel.selectedOptions[0];
        const cat = opt ? opt.dataset.category : '';
        const allowed = allClasses.filter(function (c) { return !cat || c.cat === cat; });
        const matched = allowed.length;
        const previous = classSel.value;
        const keep = allowed.some(function (c) { return c.value === previous; }) ? previous : '';
        const ts = classSel.tomselect;

        if (ts) {
            ts.clearOptions(function () { return false; }); // buang semua, termasuk yang terpilih
            ts.addOption({ value: '', text: '— Pilih Kelas —' });
            allowed.forEach(function (c) { ts.addOption({ value: c.value, text: c.text }); });
            ts.refreshOptions(false);
            ts.setValue(keep, true); // silent: jangan picu event change
        } else {
            Array.from(classSel.options).forEach(function (o) {
                if (!o.value) return; // biarkan placeholder
                const ok = !cat || o.dataset.category === cat;
                o.hidden = !ok;
                o.disabled = !ok;
            });
            classSel.value = keep;
        }

        if (hint) {
            if (!cat) {
                hint.innerHTML = '<i class="bi bi-funnel me-1"></i>Pilih murid dulu untuk menyaring slot yang cocok dengan tipe kelasnya.';
            } else if (matched === 0) {
                hint.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak ada slot ' + cat + ' yang tersedia untuk murid ini.</span>';
            } else {
                hint.innerHTML = '<i class="bi bi-funnel me-1"></i>Menampilkan ' + matched + ' slot ' + cat + ' yang tersedia untuk murid ini.';
            }
        }

        // Murid sudah dipilih tapi tak ada slot cocok → tampilkan panduan & cegah submit.
        const blocked = !!cat && matched === 0;
        if (noSlotAlert) {
            noSlotAlert.classList.toggle('d-none', !blocked);
            const reason = document.getElementById('noSlotReason');
            if (blocked && reason) {
                reason.textContent = 'Tidak ada kelas bertipe "' + cat + '" yang bisa dipakai: slot yang ada sedang penuh, ditutup, sudah lewat jadwalnya, jatuh di hari libur, atau tutornya belum aktif.';
            }
        }
        if (submitBtn) {
            submitBtn.disabled = blocked;
            submitBtn.title = blocked ? 'Tidak ada slot pengganti yang tersedia untuk murid ini' : '';
        }
        // Kelas wajib diisi hanya bila ada pilihan — hindari pesan "required" yang membingungkan.
        classSel.required = !blocked;
    }

    studentSel.addEventListener('change', filterClasses);
    filterClasses(); // jalankan saat load (mode edit / old input)
});
</script>
@endpush
