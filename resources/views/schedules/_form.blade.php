<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                <option value="{{ $student->id }}" @selected(old('student_id', $request->student_id ?? '') == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kelas Tujuan Replacement</label>
        @php $availableCount = $classes->filter->isAvailable()->count(); @endphp
        <select name="class_id" class="form-select" required>
            <option value="">— Pilih Kelas —</option>
            @foreach($classes as $class)
                @php $selected = old('class_id', $request->class_id ?? '') == $class->id; @endphp
                {{-- Hanya tampilkan slot yang tersedia; kelas yang sedang dipilih (saat edit) tetap muncul. --}}
                @if($class->isAvailable() || $selected)
                    <option value="{{ $class->id }}" @selected($selected)>
                        {{ $class->class_name }} — {{ $class->availability()['text'] }}
                    </option>
                @endif
            @endforeach
        </select>
        @if($availableCount === 0)
            <small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak ada slot yang tersedia saat ini. Buka salah satu slot lewat menu Jadwal atau Kelas.</small>
        @else
            <small class="text-muted"><i class="bi bi-funnel me-1"></i>Hanya menampilkan {{ $availableCount }} slot yang benar-benar tersedia.</small>
        @endif
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Pengganti</label>
        <input type="date" name="replacement_date" class="form-control" value="{{ old('replacement_date', isset($request) ? $request->replacement_date->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jam Pengganti</label>
        <input type="time" name="replacement_time" class="form-control" value="{{ old('replacement_time', isset($request) ? \Illuminate\Support\Str::of($request->replacement_time)->substr(0,5) : '') }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Alasan</label>
        <textarea name="reason" class="form-control" rows="2">{{ old('reason', $request->reason ?? '') }}</textarea>
    </div>
</div>
