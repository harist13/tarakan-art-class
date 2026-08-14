<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama Kelas</label>
        <input type="text" name="class_name" class="form-control" value="{{ old('class_name', $class->class_name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kategori</label>
        <select name="class_category" class="form-select" required>
            @foreach(['preschool' => 'Preschool', 'coloring' => 'Coloring', 'drawing' => 'Drawing'] as $val => $label)
                <option value="{{ $val }}" @selected(old('class_category', $class->class_category ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tutor</label>
        <select name="tutor_id" class="form-select" required>
            <option value="">— Pilih Tutor —</option>
            @foreach($tutors as $tutor)
                <option value="{{ $tutor->id }}" @selected(old('tutor_id', $class->tutor_id ?? '') == $tutor->id)>{{ $tutor->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kapasitas</label>
        <input type="number" name="capacity" min="1" class="form-control" value="{{ old('capacity', $class->capacity ?? '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Tanggal Kelas</label>
        <input type="date" name="schedule_date" id="schedule_date" class="form-control @error('schedule_date') is-invalid @enderror"
            value="{{ old('schedule_date', isset($class) ? $class->schedule_date->format('Y-m-d') : now()->toDateString()) }}" required>
        @error('schedule_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1" id="scheduleDateHint"><i class="bi bi-calendar-event me-1"></i>Hari kelas diambil dari tanggal ini.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Jam</label>
        <input type="time" name="schedule_time" class="form-control" value="{{ old('schedule_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_time)->substr(0,5) : '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label d-block">Pengulangan</label>
        {{-- Hidden dulu: checkbox yang tidak dicentang tidak ikut terkirim, jadi
             tanpa ini "kelas sekali jalan" tak pernah sampai ke server. --}}
        <input type="hidden" name="is_recurring" value="0">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" role="switch" name="is_recurring" id="is_recurring" value="1"
                @checked((bool) old('is_recurring', $class->is_recurring ?? true))>
            <label class="form-check-label" for="is_recurring">Kelas berjalan setiap minggu</label>
        </div>
        <small class="text-muted d-block mt-1" id="recurringHint"><i class="bi bi-arrow-repeat me-1"></i>Kelas berulang tiap pekan sejak tanggal di samping.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Biaya Kelas (Rp)</label>
        <input type="number" step="1000" min="0" name="class_fee" class="form-control" value="{{ old('class_fee', $class->class_fee ?? '') }}" required>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('schedule_date');
    const recurring = document.getElementById('is_recurring');
    const dateHint = document.getElementById('scheduleDateHint');
    const recurringHint = document.getElementById('recurringHint');
    if (!dateInput || !recurring) return;

    const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    function hariTerpilih() {
        const p = dateInput.value.split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);

        return isNaN(d.getTime()) ? '' : DAY_NAMES[d.getDay()];
    }

    // Hari kelas tidak diisi admin — diturunkan dari tanggal. Ditampilkan di sini
    // supaya admin tahu hari apa yang sebenarnya ia pilih.
    function updateHints() {
        const hari = hariTerpilih();

        if (dateHint) {
            dateHint.innerHTML = hari
                ? '<i class="bi bi-calendar-event me-1"></i>Tanggal ini jatuh pada hari <strong>' + hari + '</strong>.'
                : '<i class="bi bi-calendar-event me-1"></i>Hari kelas diambil dari tanggal ini.';
        }

        if (recurringHint) {
            if (recurring.checked) {
                recurringHint.innerHTML = hari
                    ? '<i class="bi bi-arrow-repeat me-1"></i>Kelas berulang <strong>tiap ' + hari + '</strong> sejak tanggal itu, sampai statusnya ditutup.'
                    : '<i class="bi bi-arrow-repeat me-1"></i>Kelas berulang tiap pekan sejak tanggal di samping.';
            } else {
                recurringHint.innerHTML = '<i class="bi bi-calendar-x me-1"></i>Kelas hanya berjalan sekali pada tanggal itu, lalu ditandai sudah lewat.';
            }
        }
    }

    dateInput.addEventListener('input', updateHints);
    recurring.addEventListener('change', updateHints);
    updateHints();
});
</script>
@endpush
