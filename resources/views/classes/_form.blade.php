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
        <label class="form-label">Tanggal Jadwal</label>
        <input type="date" name="schedule_date" class="form-control" value="{{ old('schedule_date', isset($class) ? $class->schedule_date->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Jam</label>
        <input type="time" name="schedule_time" class="form-control" value="{{ old('schedule_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_time)->substr(0,5) : '') }}" required>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Biaya Kelas (Rp)</label>
        <input type="number" step="1000" min="0" name="class_fee" class="form-control" value="{{ old('class_fee', $class->class_fee ?? '') }}" required>
    </div>
</div>
