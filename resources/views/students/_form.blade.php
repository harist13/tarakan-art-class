{{-- ─── Bagian 1: Data Murid & Akademik ────────────────────────

     Tanpa kepala bagian: label tiap kolom sudah menyebut isinya, dan kalimat
     pengantar yang cuma mengulang label hanya menambah tinggi halaman. --}}
<div class="mb-4">
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
                            $isCurrentStudentType = isset($student) && mb_strtolower($student->class_type) === mb_strtolower($cat);

                            // Satu-satunya syarat sebuah kategori bisa dipilih: ada slot
                            // yang benar-benar bisa diisi. Dulu yang menghalangi hanya
                            // "semua ditutup" & "semua penuh", sehingga kategori yang
                            // slotnya sudah lewat — trial class yang tanggalnya terlewat,
                            // misalnya — tetap ditawarkan sebagai Tersedia, lalu mentok
                            // di dropdown jadwal yang seluruh pilihannya mati.
                            $slotSiap = $catClasses->filter->isAvailable();

                            $statusText = '';
                            $statusKey = 'available';
                            $canSelect = $slotSiap->isNotEmpty();

                            if (!$hasClasses) {
                                $statusText = ' — Belum ada kelas';
                                $statusKey = 'none';
                            } elseif ($canSelect) {
                                // Kursi dihitung hanya dari slot yang bisa diisi. Menjumlah
                                // seluruh slot membuat kelas yang sudah lewat ikut
                                // menyumbang kursi yang tak bisa ditempati siapa pun.
                                $remaining = $slotSiap->sum(fn ($c) => $c->remainingSeats());
                                $statusText = " — Tersedia ({$remaining} kursi)";
                            } else {
                                // Alasannya diambil dari availability() — sumber yang sama
                                // dengan badge di Manajemen Kelas, jadi keduanya tak pernah
                                // berbeda pendapat soal kenapa sebuah slot tak bisa diisi.
                                $alasan = $catClasses->map(fn ($c) => $c->availability()['text'])->unique();
                                $statusText = ' — '.($alasan->count() === 1 ? $alasan->first() : 'Tidak ada slot tersedia');
                                $statusKey = 'unavailable';
                            }

                            if ($isCurrentStudentType) {
                                $canSelect = true;
                            }
                        @endphp
                        <option value="{{ $cat }}"
                            data-available="{{ $canSelect ? '1' : '0' }}"
                            data-status="{{ $statusKey }}"
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

            {{-- Yang dilihat admin: kotak baca-saja berisi jadwal yang akan dipakai.
                 Jadwalnya sepenuhnya ditentukan kategori, jadi tidak ada keputusan
                 yang perlu diambil di sini. --}}
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-week"></i></span>
                <input type="text" id="class_id_display" class="form-control bg-body-secondary @error('class_id') is-invalid @enderror"
                       value="Pilih kategori kelas dulu" readonly tabindex="-1" aria-label="Jadwal kelas yang akan diisi murid ini">
                @error('class_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            {{-- Pembawa nilai sekaligus sumber datanya. <select> tidak mengenal
                 readonly — hanya disabled, dan yang disabled tidak ikut terkirim.
                 Jadi select-nya disembunyikan, bukan dimatikan: penyaring di bawah
                 tetap memakainya untuk memilih slot & membaca biayanya, dan
                 nilainya tetap sampai ke server.

                 data-no-search: jangan dibungkus Tom Select. Tom Select menyalin
                 daftar opsi sekali saat inisialisasi dan tidak pernah membacanya
                 lagi, sehingga slot yang dinonaktifkan penyaring tetap terpilih. --}}
            <div class="d-none">
                <select name="class_id" id="class_id" class="form-select" data-no-search tabindex="-1" aria-hidden="true">
                    <option value="">-- Pilihkan otomatis --</option>
                    @foreach($classes as $slot)
                        @php
                            $slotAv = $slot->availability();
                            // Kelas yang sedang diikuti murid ini tetap bisa dipilih
                            // walau penuh — dialah salah satu yang mengisinya.
                            $slotSelectable = $slot->isAvailable() || $selectedClassId === $slot->id;
                            // Pertemuan pertama anak ini kalau ia masuk slot tersebut.
                            // Inilah yang menentukan berapa pekan ditagih di bulan
                            // pertama — bukan tanggal orang tuanya mendaftar.
                            $sesiPertama = $slot->nextOccurrence();
                        @endphp
                        {{-- Ketaklayakan ditegaskan sejak dari server, bukan hanya oleh
                             penyaring di bawah: slot penuh harus sudah tak bisa dipilih
                             pada gambar pertama halaman, sebelum JavaScript sempat jalan
                             atau kalau ia gagal dimuat sama sekali. --}}
                        <option value="{{ $slot->id }}"
                            data-category="{{ mb_strtolower(trim((string) $slot->class_category)) }}"
                            data-selectable="{{ $slotSelectable ? '1' : '0' }}"
                            data-session-date="{{ $sesiPertama?->toDateString() }}"
                            data-session-label="{{ $sesiPertama?->locale('id')->translatedFormat('l, j F Y') }}"
                            data-fee="{{ (int) $slot->class_fee }}"
                            data-registration="{{ (int) $slot->registration_fee }}"
                            @disabled(! $slotSelectable)
                            @selected($selectedClassId === $slot->id)>
                            {{ $slot->scheduleLabel() }} — {{ $slot->class_code }} ({{ $slotAv['text'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            {{-- Menyebut jadwal yang benar-benar akan dipakai, termasuk saat
                 dibiarkan otomatis — admin harus tahu anak ini masuk hari apa
                 sebelum menyimpan, bukan sesudah orang tuanya bertanya. --}}
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
        </div>

        {{-- Pekan mulai menentukan harga bulan pertama murid ini, dan hanya bulan
             pertama. Tersimpan pada pendaftarannya ke kelas, bukan pada muridnya:
             satu kelas dimasuki anak-anak yang datang di pekan berbeda-beda. --}}
        <div class="col-md-6">
            <label class="form-label fw-semibold">Mulai Minggu Ke-</label>
            @php
                $pekanTerpilih = (int) old('start_week', isset($student) ? $student->startWeek() : 1) ?: 1;
                $pekanSebulan = \App\Models\ClassRoom::WEEKS_PER_MONTH;
            @endphp
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar2-week"></i></span>
                <select name="start_week" id="start_week" class="form-select @error('start_week') is-invalid @enderror" data-no-search>
                    @foreach(\App\Models\ClassRoom::START_WEEKS as $week)
                        <option value="{{ $week }}" @selected($pekanTerpilih === $week)>Minggu ke-{{ $week }}</option>
                    @endforeach
                </select>
                @error('start_week') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="form-text mt-1" id="start_week_hint">
                <i class="bi bi-cash-coin me-1"></i>Bayar {{ $pekanSebulan - $pekanTerpilih + 1 }} dari {{ $pekanSebulan }} pekan di bulan pertama. Terisi otomatis dari tanggal bergabung.
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Sengaja tidak menunggu DOMContentLoaded: penyaring jadwal di blok berikutnya
// mengabarkan sesi pertama secara langsung saat dijalankan, dan itu terjadi
// sebelum DOMContentLoaded menyala. Pendengarnya harus sudah terpasang lebih
// dulu, kalau tidak kabar pertama — yang justru menentukan keadaan awal —
// lewat begitu saja. Skrip ini dirender di ujung body, jadi elemennya pasti ada.
(function () {
    const joinDate = document.getElementById('join_date');
    const startWeek = document.getElementById('start_week');
    const startWeekHint = document.getElementById('start_week_hint');
    if (!joinDate || !startWeek) return;

    const PEKAN_SEBULAN = {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }};

    // Pekan mulai mengikuti sendiri sampai admin memilih sendiri, dan sejak itu
    // pilihannya tidak lagi ditimpa. Anak yang didaftarkan hari ini tapi baru
    // mulai pekan depan memang ada, dan angkanya tidak boleh berubah di belakang
    // admin.
    let disentuh = {{ old('start_week') !== null || isset($student) ? 'true' : 'false' }};

    // Tanggal pertemuan pertama di kelas yang sedang terpilih (YYYY-MM-DD), atau
    // null bila belum ada kelas.
    //
    // Inilah yang menentukan harga, BUKAN tanggal bergabung. Yang dijawab
    // harganya adalah "anak ini dapat berapa pekan di bulan pertamanya", dan itu
    // ditentukan kapan ia mulai masuk — bukan kapan orang tuanya mendaftar.
    // Bedanya nyata: daftar 29 Agustus untuk kelas yang mulai 3 September dulu
    // terbaca sebagai pekan ke-4 dan hanya ditagih seperempat, padahal anaknya
    // dapat sebulan penuh. Tanggal bergabung tetap dipakai sebagai cadangan,
    // untuk murid yang belum punya kelas sama sekali.
    let sesiPertama = null;

    // Biaya kelas yang sedang terpilih, untuk menerjemahkan pekan jadi rupiah.
    let biayaKelas = 0;
    let biayaPendaftaran = 0;

    // Uang pendaftaran hanya menempel pada invoice pertama murid. Murid yang
    // sudah pernah ditagih tidak lagi kena, jadi jangan sampai keterangan di
    // form edit menjanjikan angka yang tidak akan muncul di invoicenya.
    const pendaftaranBerlaku = {{ (! isset($student) || ! $student->hasBeenBilled()) ? 'true' : 'false' }};

    const rupiah = function (angka) {
        return 'Rp ' + Math.round(angka).toLocaleString('id-ID');
    };

    const pekanDari = function (iso) {
        const hari = Number((iso || '').split('-')[2]);

        return hari ? Math.min(PEKAN_SEBULAN, Math.floor((hari - 1) / 7) + 1) : null;
    };

    // Keterangan menyebut akibat pilihannya, bukan mengulang angkanya: "Minggu
    // ke-3" sudah terbaca di kotaknya sendiri, yang belum terjawab adalah
    // "berarti bayar berapa pekan" — dan dari mana angka itu datang.
    function render(sumber) {
        if (!startWeekHint) return;

        const pekan = Number(startWeek.value) || 1;
        const sisa = PEKAN_SEBULAN - pekan + 1;
        const asal = {
            manual: ' Dipilih manual — tidak lagi mengikuti jadwal kelas.',
            sesi: ' Mengikuti pertemuan pertama di kelas yang dipilih.',
            tanggal: ' Terisi otomatis dari tanggal bergabung.',
        }[sumber] || '';

        // Rumusnya disalin dari ClassRoom::feeForStartWeek() — pratinjau, yang
        // mengikat tetap hitungan server saat invoice diterbitkan.
        const iuran = biayaKelas ? Math.round(biayaKelas / PEKAN_SEBULAN * sisa) : 0;
        const pendaftaran = pendaftaranBerlaku ? biayaPendaftaran : 0;

        let nominal = '';
        if (iuran > 0) {
            nominal = ' — <strong>' + rupiah(iuran) + '</strong>';
            if (pendaftaran > 0) {
                nominal += ', ditambah pendaftaran ' + rupiah(pendaftaran)
                    + ' → <strong>' + rupiah(iuran + pendaftaran) + '</strong> di invoice pertama';
            }
            nominal += '.';
        } else {
            nominal = ' di bulan pertama.';
        }

        startWeekHint.innerHTML = '<i class="bi bi-cash-coin me-1"></i>Bayar <strong>' + sisa + ' dari '
            + PEKAN_SEBULAN + ' pekan</strong>' + nominal + asal;
    }

    function ikuti(iso, sumber) {
        const pekan = pekanDari(iso);
        if (!pekan) return;

        startWeek.value = String(pekan);
        render(sumber);
    }

    // Dikirim oleh penyaring jadwal di bawah setiap kali slot yang akan dipakai
    // berubah. Lewat event, bukan panggilan langsung, supaya kedua bagian tetap
    // berdiri sendiri — yang satu tak perlu tahu isi yang lain.
    document.addEventListener('jadwal:sesi-pertama', function (e) {
        const detail = e.detail || {};
        sesiPertama = detail.date || null;
        biayaKelas = Number(detail.fee) || 0;
        biayaPendaftaran = Number(detail.registration) || 0;

        if (disentuh) {
            // Pekannya milik admin, tapi nominalnya tetap harus ikut kelas yang
            // baru dipilih — kalau tidak, angka rupiahnya masih milik kelas lama.
            render('manual');

            return;
        }

        ikuti(sesiPertama || joinDate.value, sesiPertama ? 'sesi' : 'tanggal');
    });

    startWeek.addEventListener('change', function () {
        disentuh = true;
        render('manual');
    });

    joinDate.addEventListener('input', function () {
        // Selama kelasnya sudah diketahui, pertemuan pertamanyalah yang berlaku;
        // tanggal bergabung hanya cadangan saat belum ada kelas.
        if (disentuh || sesiPertama) return;

        ikuti(joinDate.value, 'tanggal');
    });
})();
</script>
@endpush

{{-- ─── Bagian 2: Data Wali & Kontak ───────────────────────────

     Tanpa pemisah apa pun: jarak antar baris sudah cukup memisahkan, dan garis
     mendatar di form sepanjang ini hanya memotongnya tanpa menambah keterangan. --}}
<div class="mb-4">

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
            } else if (status === 'unavailable') {
                // Alasannya datang dari availability() di server — penuh, ditutup,
                // tutor kosong, atau sudah lewat. Satu cabang, bukan satu per
                // sebab: daftar sebab di JavaScript pasti ketinggalan begitu ada
                // alasan baru, dan itu yang membuat "sudah lewat" sempat lolos
                // sebagai "Tersedia".
                const alasan = selected.dataset.statusText ? selected.dataset.statusText.replace(/^ — /, '') : 'Tidak ada slot tersedia';
                hint.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i><strong>' + alasan + '</strong> — tidak ada jadwal yang bisa diisi untuk tipe ini.';
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
        // Kotak baca-saja yang dilihat admin; select-nya sendiri tersembunyi dan
        // hanya bertugas membawa nilai ke server.
        const slotDisplay = document.getElementById('class_id_display');
        if (!slotSelect || !slotHint) return;

        // Ditandai supaya keterangannya jujur: nilai yang diisikan kategori bukan
        // pilihan admin, jadi tidak boleh dilaporkan sebagai "dipilih manual".
        let diisiKategori = false;

        /**
         * @param {boolean} isiOtomatis Isikan jadwal pertama yang muat di kategori
         *   ini bila belum ada yang terpilih. Hanya saat kategorinya baru berganti
         *   — kalau dijalankan pada tiap perubahan, admin yang sengaja memilih
         *   "Pilihkan otomatis" akan langsung ditimpa kembali.
         */
        function syncSlots(isiOtomatis) {
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
                diisiKategori = false;
            }

            // Jadwal pertama yang muat di kategori ini — aturannya sama persis
            // dengan resolveClass() di server, jadi yang terbaca di layar adalah
            // yang benar-benar akan tersimpan.
            const pertama = Array.from(slotSelect.options).find(function (opt) {
                return opt.value && !opt.hidden && !opt.disabled;
            });

            // Kategori yang baru dipilih langsung membawa jadwalnya. Yang sudah
            // terisi tidak diganggu: pilihan admin, maupun slot yang dibawa dari
            // layar Ketersediaan Slot, harus bertahan.
            if (isiOtomatis && pertama && slotSelect.value === '') {
                slotSelect.value = pertama.value;
                diisiKategori = true;
            }

            const adaNilai = slotSelect.value !== '';
            const dipakai = adaNilai ? slotSelect.selectedOptions[0] : pertama;

            // Pertemuan pertama slot yang akan dipakai — dasar harga bulan
            // pertama. Dikabarkan ke pengisi "Mulai Minggu Ke-" lewat event
            // supaya kedua bagian tetap berdiri sendiri.
            const sesiTanggal = dipakai ? (dipakai.dataset.sessionDate || '') : '';
            const sesiLabel = dipakai ? (dipakai.dataset.sessionLabel || '') : '';

            document.dispatchEvent(new CustomEvent('jadwal:sesi-pertama', {
                detail: {
                    date: sesiTanggal,
                    label: sesiLabel,
                    // Biayanya ikut dikabarkan supaya keterangan pekan bisa
                    // menyebut rupiahnya — angka pekan saja menuntut admin
                    // membagi sendiri sebelum bisa menjawab orang tua.
                    fee: dipakai ? Number(dipakai.dataset.fee || 0) : 0,
                    registration: dipakai ? Number(dipakai.dataset.registration || 0) : 0,
                },
            }));

            // Kotak baca-saja adalah satu-satunya yang dilihat admin, jadi ia yang
            // harus menyatakan keadaan — termasuk saat tidak ada jadwal yang bisa
            // dipakai. Kotak kosong tanpa keterangan akan terbaca sebagai
            // "belum terisi", padahal masalahnya kelasnya yang tidak ada.
            const setDisplay = function (teks) {
                if (slotDisplay) slotDisplay.value = teks;
            };

            if (kategori === '') {
                setDisplay('Pilih kategori kelas dulu');
                slotHint.innerHTML = '<i class="bi bi-info-circle me-1"></i>Jadwalnya mengikuti kategori yang dipilih.';
                slotHint.className = 'form-text mt-1 text-muted';
            } else if (sekategori === 0) {
                setDisplay('Belum ada jadwal untuk kategori ini');
                slotHint.innerHTML = '<i class="bi bi-info-circle-fill me-1"></i>Tambahkan kelasnya dulu di menu Manajemen Kelas.';
                slotHint.className = 'form-text mt-1 text-warning';
            } else if (bisaDipilih === 0) {
                setDisplay('Semua jadwal penuh atau sudah lewat');
                slotHint.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i>Tidak ada jadwal yang bisa diisi untuk kategori ini.';
                slotHint.className = 'form-text mt-1 text-danger';
            } else {
                const label = (dipakai ? dipakai.textContent : '').trim();
                setDisplay(label);

                // Tanggal pertemuan pertamanya disebutkan supaya angka pekan &
                // nominal invoice di bawah bisa ditelusuri admin, bukan muncul
                // begitu saja.
                slotHint.innerHTML = sesiLabel
                    ? '<i class="bi bi-calendar-event me-1"></i>Pertemuan pertamanya <strong>' + sesiLabel + '</strong>.'
                    : '<i class="bi bi-calendar-check me-1"></i>Mengikuti kategori yang dipilih.';
                slotHint.className = 'form-text mt-1 text-muted';
            }
        }

        // Saat halaman dibuka, kategori bisa saja sudah terisi — form edit, kiriman
        // yang gagal validasi, atau datang dari layar Ketersediaan Slot. Jadwalnya
        // ikut diisikan juga di sini supaya keadaan awalnya sama dengan sesudah
        // kategori diganti sendiri oleh admin.
        syncSlots(true);

        // Perubahan pada dropdown jadwal tidak boleh mengisi apa pun: itu justru
        // pilihan admin, termasuk saat ia mengembalikannya ke "Pilihkan otomatis".
        slotSelect.addEventListener('change', function () {
            diisiKategori = false;
            syncSlots(false);
        });

        typeSelect.addEventListener('change', function () {
            syncSlots(true);
        });
    })();
</script>
@endpush
