{{-- ─── Bagian 1: Data Murid & Akademik ──────────────────────── --}}
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
        <span class="badge rounded-circle p-2 bg-primary-subtle text-primary">
            <i class="bi bi-person-badge fs-6"></i>
        </span>
        <div>
            <h6 class="fw-bold mb-0 text-dark">Data Murid & Kelas</h6>
            <small class="text-muted">Informasi identitas murid dan program kelas yang diikuti</small>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Nama Lengkap Murid <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $student->name ?? '') }}"
                       placeholder="Contoh: Muhammad Harist" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tipe / Kategori Kelas <span class="text-danger">*</span></label>
            @php
                $selectedType = old('class_type', $student->class_type ?? '');
                $categories = $classes->pluck('class_category')->filter(fn ($c) => !empty(trim($c)))->unique()->values();
            @endphp
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-palette"></i></span>
                <select name="class_type" id="class_type" class="form-select @error('class_type') is-invalid @enderror" required>
                    <option value="" @selected($selectedType === '' || $selectedType === null)>-- Pilih Kategori Kelas --</option>
                    @forelse($categories as $cat)
                        @php
                            $catClasses = $classes->filter(fn ($c) => mb_strtolower($c->class_category) === mb_strtolower($cat));
                            $hasClasses = $catClasses->isNotEmpty();
                            $availClass = $catClasses->first(fn ($c) => $c->isAvailable());
                            $isClosed = $hasClasses && $catClasses->every(fn ($c) => $c->isClosed());
                            $isFull = $hasClasses && $catClasses->every(fn ($c) => $c->isFull());
                            $isCurrentStudentType = isset($student) && mb_strtolower($student->class_type) === mb_strtolower($cat);

                            $statusText = '';
                            $canSelect = true;
                            if (!$hasClasses) {
                                $statusText = ' — Belum ada kelas';
                                $canSelect = false;
                            } elseif ($isClosed) {
                                $statusText = ' — Kelas Ditutup';
                                $canSelect = false;
                            } elseif ($isFull) {
                                $statusText = ' — Kelas Penuh';
                                $canSelect = false;
                            } else {
                                $remaining = $catClasses->sum(fn ($c) => $c->remainingSeats());
                                $statusText = " — Tersedia ({$remaining} kursi)";
                            }

                            if ($isCurrentStudentType) {
                                $canSelect = true;
                            }
                        @endphp
                        <option value="{{ $cat }}"
                            data-available="{{ $canSelect ? '1' : '0' }}"
                            data-status="{{ !$hasClasses ? 'none' : ($isClosed ? 'closed' : ($isFull ? 'full' : 'available')) }}"
                            data-status-text="{{ $statusText }}"
                            @selected(mb_strtolower($selectedType) === mb_strtolower($cat))
                            @disabled(! $canSelect)>
                            {{ $cat }}{{ $statusText }}
                        </option>
                    @empty
                        <option value="" disabled>Belum ada kategori kelas di sistem. Tambahkan di menu Manajemen Kelas.</option>
                    @endforelse
                </select>
                @error('class_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text mt-1" id="class_type_hint">
                <i class="bi bi-info-circle me-1"></i>Pilih kategori kelas yang diambil dari data kelas.
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tanggal Lahir <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
                <input type="date" name="date_of_birth" id="date_of_birth"
                       class="form-control @error('date_of_birth') is-invalid @enderror"
                       value="{{ old('date_of_birth', isset($student) ? $student->date_of_birth->format('Y-m-d') : '') }}"
                       max="{{ now()->toDateString() }}" required>
                @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Usia Murid</label>
            <div class="input-group">
                <input type="number" name="age" id="age" class="form-control @error('age') is-invalid @enderror"
                       min="0" max="120" step="1"
                       value="{{ old('age', isset($student) ? $student->age : '') }}"
                       placeholder="Otomatis dari tanggal lahir">
                <span class="input-group-text bg-light">tahun</span>
                @error('age') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text mt-1" id="age_hint">
                <i class="bi bi-magic me-1"></i>Terisi otomatis saat tanggal lahir diisi.
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Status Keaktifan <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-toggle-on"></i></span>
                <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Aktif (Mengikuti Kelas)</option>
                    <option value="inactive" @selected(old('status', $student->status ?? '') === 'inactive')>Nonaktif (Cuti / Berhenti)</option>
                </select>
                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tanggal Bergabung <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-check"></i></span>
                <input type="date" name="join_date"
                       class="form-control @error('join_date') is-invalid @enderror"
                       value="{{ old('join_date', isset($student) ? $student->join_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
                @error('join_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

{{-- ─── Bagian 2: Data Wali & Kontak ─────────────────────────── --}}
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom">
        <span class="badge rounded-circle p-2 bg-success-subtle text-success">
            <i class="bi bi-people fs-6"></i>
        </span>
        <div>
            <h6 class="fw-bold mb-0 text-dark">Informasi Wali & Kontak</h6>
            <small class="text-muted">Kontak orang tua / wali untuk konfirmasi jadwal dan informasi akademik</small>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Nama Wali / Orang Tua <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person-heart"></i></span>
                <input type="text" name="parent_name"
                       class="form-control @error('parent_name') is-invalid @enderror"
                       value="{{ old('parent_name', $student->parent_name ?? '') }}"
                       placeholder="Contoh: Ibu Rina / Bapak Budi" required>
                @error('parent_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">No. WhatsApp / HP Wali <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light text-success"><i class="bi bi-whatsapp"></i></span>
                <input type="tel" name="phone_number"
                       class="form-control @error('phone_number') is-invalid @enderror"
                       value="{{ old('phone_number', $student->phone_number ?? '') }}"
                       inputmode="numeric" pattern="[0-9]+" maxlength="20"
                       placeholder="081234567890"
                       oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text mt-1">Hanya angka, contoh: <code>081234567890</code></div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Instagram (opsional)</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted">@</span>
                <input type="text" name="instagram_username"
                       class="form-control @error('instagram_username') is-invalid @enderror"
                       value="{{ old('instagram_username', $student->instagram_username ?? '') }}"
                       placeholder="username_instagram">
                @error('instagram_username') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Alamat Tempat Tinggal (opsional)</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-geo-alt"></i></span>
                <textarea name="address"
                          class="form-control @error('address') is-invalid @enderror"
                          rows="1"
                          placeholder="Nama jalan, perumahan, kelurahan">{{ old('address', $student->address ?? '') }}</textarea>
                @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
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
            hint.innerHTML = manual
                ? '<i class="bi bi-pencil me-1"></i>Usia diisi manual. Kosongkan untuk menghitung ulang dari tanggal lahir.'
                : '<i class="bi bi-magic me-1"></i>Terisi otomatis saat tanggal lahir diisi.';
        }

        function fillFromDob(force) {
            const auto = computeAge();
            if (auto !== null && (force || !manual)) {
                manual = false;
                ageInput.value = auto;
            }
            updateHint();
        }

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
        const hint = document.getElementById('class_type_hint');
        if (!typeSelect || !hint) return;

        function updateTypeHint() {
            const selected = typeSelect.selectedOptions[0];
            if (!selected || !selected.value) {
                hint.innerHTML = '<i class="bi bi-info-circle me-1"></i>Pilih tipe kelas yang sesuai untuk murid.';
                hint.className = 'form-text mt-1 text-muted';
                return;
            }

            const status = selected.dataset.status;
            if (status === 'available') {
                const label = selected.dataset.statusText ? selected.dataset.statusText.replace(/^ — /, '') : 'Kelas tersedia';
                hint.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i><strong>' + label + '</strong> untuk tipe ini.';
                hint.className = 'form-text mt-1 text-success';
            } else if (status === 'full') {
                hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Semua kelas pada kategori ini sudah penuh.</strong>';
                hint.className = 'form-text mt-1 text-danger';
            } else if (status === 'closed') {
                hint.innerHTML = '<i class="bi bi-lock-fill me-1"></i><strong>Kelas pada kategori ini sedang ditutup.</strong>';
                hint.className = 'form-text mt-1 text-danger';
            } else if (status === 'none') {
                hint.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i>Belum ada jadwal kelas untuk kategori ini di sistem.';
                hint.className = 'form-text mt-1 text-warning';
            }
        }

        updateTypeHint();
        typeSelect.addEventListener('change', updateTypeHint);
    })();
</script>
@endpush
