@php $selectedClass = old('class_id', isset($student) ? optional($student->classes->first())->id : ''); @endphp
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Nama Murid</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $student->name ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Lahir</label>
        <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" value="{{ old('date_of_birth', isset($student) ? $student->date_of_birth->format('Y-m-d') : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Usia</label>
        <div class="input-group">
            <input type="number" name="age" id="age" class="form-control" min="0" max="120" step="1"
                   value="{{ old('age', isset($student) ? $student->age : '') }}"
                   placeholder="Terisi otomatis dari tanggal lahir">
            <span class="input-group-text">tahun</span>
        </div>
        <div class="form-text" id="age_hint">Terisi otomatis saat tanggal lahir diisi</div>
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
        @php $selectedType = old('class_type', $student->class_type ?? ''); @endphp
        <select name="class_type" id="class_type" class="form-select" required>
            <option value="" @selected($selectedType === '' || $selectedType === null)>-- Pilih Tipe Kelas --</option>
            @foreach(['preschool' => 'Preschool', 'coloring' => 'Coloring', 'drawing' => 'Drawing'] as $val => $label)
                <option value="{{ $val }}" @selected($selectedType === $val)>{{ $label }}</option>
            @endforeach
        </select>
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
    <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $student->status ?? '') === 'inactive')>Nonaktif</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Bergabung</label>
        <input type="date" name="join_date" class="form-control" value="{{ old('join_date', isset($student) ? $student->join_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Alamat (opsional)</label>
        <textarea name="address" class="form-control" rows="2">{{ old('address', $student->address ?? '') }}</textarea>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        // Usia terisi otomatis dari tanggal lahir, tapi tetap boleh diisi/diubah manual.
        const dobInput = document.getElementById('date_of_birth');
        const ageInput = document.getElementById('age');
        const hint = document.getElementById('age_hint');
        if (!dobInput || !ageInput) return;

        // Nilai bawaan dianggap manual bila berbeda dari hitungan tanggal lahir (kasus edit).
        let manual = false;

        function computeAge() {
            const dob = new Date(dobInput.value);
            if (!dobInput.value || isNaN(dob)) return null;

            const now = new Date();
            if (dob > now) return null;

            let years = now.getFullYear() - dob.getFullYear();
            const monthDiff = now.getMonth() - dob.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < dob.getDate())) years--;

            return years;
        }

        function updateHint() {
            if (!hint) return;
            hint.textContent = manual
                ? 'Usia diisi manual. Kosongkan untuk menghitung ulang dari tanggal lahir.'
                : 'Terisi otomatis saat tanggal lahir diisi.';
        }

        function fillFromDob(force) {
            const auto = computeAge();
            // Nilai manual tidak ditimpa kecuali kolom usia dikosongkan.
            if (auto !== null && (force || !manual)) {
                manual = false;
                ageInput.value = auto;
            }
            updateHint();
        }

        // Saat load: isi bila kosong, dan tandai manual bila nilai tersimpan beda dari hitungan.
        const initialAuto = computeAge();
        if (ageInput.value === '') {
            fillFromDob(true);
        } else {
            manual = parseInt(ageInput.value, 10) !== initialAuto;
            updateHint();
        }

        dobInput.addEventListener('change', function () { fillFromDob(false); });
        ageInput.addEventListener('input', function () {
            manual = ageInput.value !== '';
            if (!manual) { fillFromDob(true); return; }
            updateHint();
        });
    })();

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

            // Belum memilih tipe kelas: sembunyikan semua opsi kelas.
            if (!type) {
                Array.from(classSelect.options).forEach(function (opt) {
                    if (!opt.value) return;
                    opt.hidden = true;
                    opt.disabled = true;
                });
                if (resetSelection) classSelect.value = '';
                if (hint) {
                    hint.textContent = 'Pilih Tipe Kelas terlebih dahulu untuk menampilkan kelas yang sesuai.';
                    hint.classList.remove('text-danger');
                }
                return;
            }

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
