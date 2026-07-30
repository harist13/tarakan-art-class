@php $selectedClass = old('class_id', isset($student) ? optional($student->classes->first())->id : ''); @endphp
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama Murid</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $student->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Lahir</label>
        <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', isset($student) ? $student->date_of_birth->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama Wali / Orang Tua</label>
        <input type="text" name="parent_name" class="form-control" value="{{ old('parent_name', $student->parent_name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">No HP Wali</label>
        <input type="tel" name="phone_number" class="form-control" value="{{ old('phone_number', $student->phone_number ?? '') }}"
               inputmode="numeric" pattern="[0-9]+" maxlength="20" title="Hanya boleh angka"
               oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
        <div class="form-text">Hanya angka, mis. 081234567890.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Instagram (opsional)</label>
        <input type="text" name="instagram_username" class="form-control" value="{{ old('instagram_username', $student->instagram_username ?? '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tipe Kelas</label>
        <select name="class_type" id="class_type" class="form-select" required>
            @foreach(['preschool' => 'Preschool', 'coloring' => 'Coloring', 'drawing' => 'Drawing'] as $val => $label)
                <option value="{{ $val }}" @selected(old('class_type', $student->class_type ?? '') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Bergabung</label>
        <input type="date" name="join_date" class="form-control" value="{{ old('join_date', isset($student) ? $student->join_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $student->status ?? '') === 'inactive')>Nonaktif</option>
        </select>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Alamat (opsional)</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $student->address ?? '') }}</textarea>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Daftarkan ke Kelas <span class="text-danger">*</span></label>
        <select name="class_id" id="class_id" class="form-select" data-no-search required>
            <option value="" disabled @selected($selectedClass === '' || $selectedClass === null)>-- Pilih Kelas --</option>
            @foreach($classes as $class)
                @php
                    $isSelected = (string) $selectedClass === (string) $class->id;
                    $isAvail = $class->isAvailable();
                    $statusLabel = $isAvail ? '' : ($class->isClosed() ? ' — Kelas Ditutup' : ' — Kelas Penuh');
                @endphp
                <option value="{{ $class->id }}"
                    data-category="{{ $class->class_category }}"
                    data-available="{{ $isAvail ? '1' : '0' }}"
                    @selected($isSelected)
                    @disabled(! $isAvail && ! $isSelected)>
                    {{ $class->class_name }} ({{ $class->class_code }}){{ $statusLabel }}
                </option>
            @endforeach
        </select>
        <div class="form-text" id="class_id_hint">Pilih Tipe Kelas terlebih dahulu untuk menampilkan kelas yang sesuai.</div>
        @if($classes->isEmpty())
            <div class="form-text text-danger">Belum ada kelas tersedia. Tambahkan kelas terlebih dahulu.</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const typeSelect = document.getElementById('class_type');
        const classSelect = document.getElementById('class_id');
        const hint = document.getElementById('class_id_hint');
        if (!typeSelect || !classSelect) return;

        const categoryLabels = { preschool: 'Preschool', coloring: 'Coloring', drawing: 'Drawing' };
        // Kelas yang sudah terpilih saat load (kasus edit) tetap boleh dipilih walau penuh/ditutup.
        const lockedValue = classSelect.value;

        function filterClasses(resetSelection) {
            const type = typeSelect.value;
            let selectable = 0;

            Array.from(classSelect.options).forEach(function (opt) {
                if (!opt.value) return; // biarkan placeholder
                const match = opt.dataset.category === type;
                const isAvail = opt.dataset.available === '1' || opt.value === lockedValue;
                opt.hidden = !match;                 // sembunyikan yang beda tipe
                opt.disabled = !match || !isAvail;   // penuh/ditutup tetap tampil tapi tidak bisa dipilih
                if (match && isAvail) selectable++;
            });

            // Reset pilihan bila kelas terpilih tidak lagi sesuai tipe.
            const selected = classSelect.selectedOptions[0];
            if (resetSelection && selected && selected.value && selected.dataset.category !== type) {
                classSelect.value = '';
            }

            if (hint) {
                if (selectable === 0) {
                    hint.textContent = 'Tidak ada kelas ' + (categoryLabels[type] || type) + ' yang tersedia (semua penuh/ditutup).';
                    hint.classList.add('text-danger');
                } else {
                    hint.textContent = 'Menampilkan ' + selectable + ' kelas ' + (categoryLabels[type] || type) + ' yang tersedia.';
                    hint.classList.remove('text-danger');
                }
            }
        }

        // Jangan reset saat load awal (agar nilai edit/old tetap dipertahankan).
        filterClasses(false);
        typeSelect.addEventListener('change', function () { filterClasses(true); });
    })();
</script>
@endpush
