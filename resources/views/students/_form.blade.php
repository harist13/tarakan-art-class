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
                $selectedClass = $selectedClass ?? null;
                // Datang dari layar Ketersediaan Slot: kategori slot itulah yang
                // dimaksud, jadi tak perlu dipilih ulang.
                $selectedType = old('class_type', $student->class_type ?? ($selectedClass->class_category ?? ''));
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

        {{-- Jadwal yang benar-benar diisi anak ini. Sebelumnya sistem yang
             menebak — slot pertama di kategori itu yang kebetulan masih muat —
             sehingga admin tak pernah tahu anaknya masuk hari apa. Sekarang slot
             yang dilihat kosong di layar Ketersediaan Slot itu juga yang terisi. --}}
        <div class="col-md-6">
            <label class="form-label fw-semibold">Jadwal Kelas</label>
            @php $selectedClassId = (int) old('class_id', $selectedClass->id ?? 0); @endphp
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-week"></i></span>
                <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror">
                    <option value="">-- Pilihkan otomatis --</option>
                    @foreach($classes as $slot)
                        @php
                            $slotAv = $slot->availability();
                            // Kelas yang sedang diikuti murid ini tetap bisa dipilih
                            // walau penuh — dialah salah satu yang mengisinya.
                            $slotSelectable = $slot->isAvailable() || $selectedClassId === $slot->id;
                        @endphp
                        <option value="{{ $slot->id }}"
                            data-category="{{ mb_strtolower(trim((string) $slot->class_category)) }}"
                            data-selectable="{{ $slotSelectable ? '1' : '0' }}"
                            @selected($selectedClassId === $slot->id)>
                            {{ $slot->scheduleLabel() }} — {{ $slot->class_code }} ({{ $slotAv['text'] }})
                        </option>
                    @endforeach
                </select>
                @error('class_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text mt-1" id="class_id_hint">
                <i class="bi bi-info-circle me-1"></i>Pilih kategori kelas dulu untuk melihat jadwalnya.
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
                <input type="date" name="join_date" id="join_date"
                       class="form-control @error('join_date') is-invalid @enderror"
                       value="{{ old('join_date', isset($student) ? $student->join_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
                @error('join_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Pekan mulai menentukan harga bulan pertama murid ini, dan hanya bulan
                 pertama. Tersimpan pada pendaftarannya ke kelas, bukan pada muridnya:
                 satu kelas dimasuki anak-anak yang datang di pekan berbeda-beda.

                 Tampil sebagai keterangan, bukan kotak isian tersendiri: nilainya
                 turun dari tanggal di atas dan nyaris tak pernah diubah, jadi satu
                 kolom form penuh untuknya hanya menambah ramai. Yang jarang dipakai
                 disembunyikan di balik "Ubah", bukan dihilangkan. --}}
            @php
                $pekanTerpilih = (int) old('start_week', isset($student) ? $student->startWeek() : 1) ?: 1;
                $pekanSebulan = \App\Models\ClassRoom::WEEKS_PER_MONTH;
            @endphp
            <div class="form-text mt-2 d-flex align-items-center flex-wrap gap-2">
                <span>
                    <i class="bi bi-calendar2-week me-1"></i>Mulai
                    <strong id="startWeekLabel">Minggu ke-{{ $pekanTerpilih }}</strong>
                    <span id="startWeekNote">— bayar {{ $pekanSebulan - $pekanTerpilih + 1 }} dari {{ $pekanSebulan }} pekan di bulan pertama</span>
                </span>
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline text-decoration-none" id="startWeekEdit">Ubah</button>
            </div>
            <div class="mt-2 @error('start_week') @else d-none @enderror" id="startWeekPicker" style="max-width:220px;">
                <select name="start_week" id="start_week" class="form-select form-select-sm @error('start_week') is-invalid @enderror" data-no-search>
                    @foreach(\App\Models\ClassRoom::START_WEEKS as $week)
                        <option value="{{ $week }}" @selected($pekanTerpilih === $week)>Minggu ke-{{ $week }}</option>
                    @endforeach
                </select>
                @error('start_week') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const joinDate = document.getElementById('join_date');
    const startWeek = document.getElementById('start_week');
    const label = document.getElementById('startWeekLabel');
    const note = document.getElementById('startWeekNote');
    const picker = document.getElementById('startWeekPicker');
    const tombolUbah = document.getElementById('startWeekEdit');
    if (!joinDate || !startWeek) return;

    const PEKAN_SEBULAN = {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }};

    // Pekan mulai hampir selalu sama dengan pekan tanggal bergabung, jadi ia
    // mengikuti sendiri — sampai admin memilih sendiri, dan sejak itu pilihannya
    // tidak lagi ditimpa. Anak yang didaftarkan hari ini tapi baru mulai pekan
    // depan memang ada, dan angkanya tidak boleh berubah di belakang admin.
    let disentuh = {{ old('start_week') !== null || isset($student) ? 'true' : 'false' }};

    function render(manual) {
        const pekan = Number(startWeek.value) || 1;
        const sisa = PEKAN_SEBULAN - pekan + 1;

        label.textContent = 'Minggu ke-' + pekan;
        note.textContent = manual
            ? '— dipilih manual, bayar ' + sisa + ' dari ' + PEKAN_SEBULAN + ' pekan di bulan pertama'
            : '— bayar ' + sisa + ' dari ' + PEKAN_SEBULAN + ' pekan di bulan pertama';
    }

    tombolUbah?.addEventListener('click', function () {
        picker.classList.toggle('d-none');
        if (! picker.classList.contains('d-none')) {
            startWeek.focus();
        }
    });

    startWeek.addEventListener('change', function () {
        disentuh = true;
        render(true);
    });

    joinDate.addEventListener('input', function () {
        if (disentuh) return;

        const hari = Number((joinDate.value || '').split('-')[2]);
        if (!hari) return;

        startWeek.value = String(Math.min(PEKAN_SEBULAN, Math.floor((hari - 1) / 7) + 1));
        render(false);
    });
});
</script>
@endpush

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

        // ── Jadwal mengikuti kategori ──
        const slotSelect = document.getElementById('class_id');
        const slotHint = document.getElementById('class_id_hint');
        if (!slotSelect || !slotHint) return;

        function syncSlots() {
            const kategori = (typeSelect.value || '').trim().toLowerCase();
            let sekategori = 0;
            let bisaDipilih = 0;

            Array.from(slotSelect.options).forEach(function (opt) {
                if (!opt.value) return;

                const cocok = kategori !== '' && opt.dataset.category === kategori;
                opt.hidden = !cocok;
                // Sebagian browser masih bisa menyorot option yang hidden lewat
                // keyboard, jadi ketaklayakannya ditegaskan lewat disabled.
                opt.disabled = !cocok || opt.dataset.selectable === '0';

                if (cocok) {
                    sekategori++;
                    if (opt.dataset.selectable !== '0') bisaDipilih++;
                }
            });

            // Pilihan yang kategorinya sudah tak cocok dilepas, bukan dibiarkan
            // tersembunyi tapi ikut terkirim.
            const terpilih = slotSelect.selectedOptions[0];
            if (terpilih && terpilih.value && terpilih.hidden) {
                slotSelect.value = '';
            }

            if (kategori === '') {
                slotHint.innerHTML = '<i class="bi bi-info-circle me-1"></i>Pilih kategori kelas dulu untuk melihat jadwalnya.';
                slotHint.className = 'form-text mt-1 text-muted';
            } else if (sekategori === 0) {
                slotHint.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i>Belum ada jadwal untuk kategori ini.';
                slotHint.className = 'form-text mt-1 text-warning';
            } else if (bisaDipilih === 0) {
                slotHint.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Semua jadwal kategori ini penuh atau ditutup.';
                slotHint.className = 'form-text mt-1 text-danger';
            } else {
                slotHint.innerHTML = '<i class="bi bi-calendar-check me-1"></i><strong>' + bisaDipilih + ' jadwal</strong> bisa diisi. Dibiarkan kosong berarti dipilihkan otomatis.';
                slotHint.className = 'form-text mt-1 text-success';
            }
        }

        syncSlots();
        typeSelect.addEventListener('change', syncSlots);
    })();
</script>
@endpush
