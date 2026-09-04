{{-- Blok 1 — Identitas kelas: apa yang diajarkan, siapa tutornya, muat berapa
     anak, dan pola pertemuannya.

     Pola Kelas duduk di sini, bukan di blok Jadwal: ia sepenuhnya turunan dari
     Tipe Kelas di sebelahnya (trial sekali jalan, reguler mingguan), jadi
     tempatnya di samping penyebabnya — bukan di antara isian jam. --}}
<h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-easel2 me-1"></i>Informasi Kelas</h6>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-palette"></i></span>
            <input type="text" name="class_category" class="form-control @error('class_category') is-invalid @enderror" value="{{ old('class_category', $class->class_category ?? '') }}" placeholder="Contoh: Preschool, Coloring, Drawing" required>
            @error('class_category')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Tutor <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-person-video3"></i></span>
            <select name="tutor_id" class="form-select @error('tutor_id') is-invalid @enderror" required>
                <option value="">— Pilih Tutor —</option>
                @foreach($tutors as $tutor)
                    <option value="{{ $tutor->id }}" @selected(old('tutor_id', $class->tutor_id ?? '') == $tutor->id)>{{ $tutor->name }}</option>
                @endforeach
            </select>
            @error('tutor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        {{-- Tipe kelas menggantikan saklar pengulangan: trial hanya sekali pertemuan,
             reguler berjalan tiap pekan. Controller yang menurunkan is_recurring. --}}
        <label class="form-label fw-semibold">Tipe Kelas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-bookmark"></i></span>
            <select name="class_type" id="class_type" class="form-select @error('class_type') is-invalid @enderror" data-no-search required>
                @foreach(\App\Models\ClassRoom::TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('class_type', $class->class_type ?? 'regular') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('class_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Kapasitas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-people"></i></span>
            <input type="number" name="capacity" min="1" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $class->capacity ?? '') }}" placeholder="0" required>
            <span class="input-group-text">murid</span>
            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        {{-- Kotak baca-saja setinggi kontrol di sebelahnya, supaya barisnya rata.
             Kalimat panjangnya turun ke keterangan di bawah — di dalam kotak ia
             akan membuat baris ini lebih tinggi dari dua kolom lainnya. --}}
        <label class="form-label fw-semibold">Pola Kelas</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-arrow-repeat"></i></span>
            <div class="form-control bg-body-secondary" id="recurringPattern">Berulang tiap pekan</div>
        </div>
        <small class="text-muted d-block mt-1" id="recurringHint">Kelas berulang tiap pekan sejak tanggal kelasnya.</small>
    </div>
</div>

<hr class="my-4">

{{-- Blok 2 — Jadwal. Hari kelas tidak diisi admin, diturunkan dari tanggalnya. --}}
<h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-calendar-week me-1"></i>Jadwal</h6>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tanggal Kelas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
            <input type="date" name="schedule_date" id="schedule_date" class="form-control @error('schedule_date') is-invalid @enderror"
                value="{{ old('schedule_date', isset($class) ? $class->schedule_date->format('Y-m-d') : now()->toDateString()) }}" required>
            @error('schedule_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1" id="scheduleDateHint"><i class="bi bi-calendar-event me-1"></i>Hari kelas diambil dari tanggal ini.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Jam Mulai <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-clock"></i></span>
            <input type="time" name="schedule_time" id="schedule_time" class="form-control @error('schedule_time') is-invalid @enderror" value="{{ old('schedule_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_time)->substr(0,5) : '') }}" required>
            @error('schedule_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Jam Selesai <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-clock-history"></i></span>
            <input type="time" name="schedule_end_time" id="schedule_end_time" class="form-control @error('schedule_end_time') is-invalid @enderror" value="{{ old('schedule_end_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_end_time)->substr(0,5) : '') }}" required>
            @error('schedule_end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1" id="durationHint"></small>
    </div>
</div>

<hr class="my-4">

{{-- Blok 3 — Biaya. Iuran kelas dan uang pendaftaran dipisah: yang satu berulang,
     yang satu hanya ditagih saat murid mendaftar. --}}
<h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-wallet2 me-1"></i>Biaya</h6>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Biaya Kelas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted">Rp</span>
            <input type="number" step="1000" min="0" name="class_fee" id="class_fee" class="form-control @error('class_fee') is-invalid @enderror" value="{{ old('class_fee', isset($class) ? (int) $class->class_fee : '') }}" placeholder="0" required>
            @error('class_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1">Iuran per bulan, ditagih penuh mulai bulan kedua.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Uang Pendaftaran <span class="badge bg-body-secondary text-body-secondary border fw-semibold ms-1">Opsional</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted">Rp</span>
            <input type="number" step="1000" min="0" name="registration_fee" id="registration_fee" class="form-control @error('registration_fee') is-invalid @enderror" value="{{ old('registration_fee', isset($class) ? (int) $class->registration_fee : '') }}" placeholder="0">
            @error('registration_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1">Kosongkan bila kelas ini tidak memungutnya.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Total Bayar Awal</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-calculator"></i></span>
            <div class="form-control bg-body-secondary fw-bold" id="totalFeePreview">Rp 0</div>
        </div>
        <small class="text-muted d-block mt-1">Biaya kelas + uang pendaftaran.</small>
    </div>
</div>

{{-- Harga bulan pertama tidak diketik, melainkan diturunkan dari Biaya Kelas:
     murid membayar pekan yang benar-benar ia dapat. Ditampilkan di sini supaya
     admin melihat akibat angka yang baru saja ia ketik, bukan menghitung sendiri
     saat orang tua bertanya. --}}
<div class="mt-4">
    <label class="form-label mb-1">Harga Bulan Pertama menurut Pekan Murid Masuk</label>
    <p class="text-muted small mb-3">
        Dihitung otomatis: sebulan {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }} pekan, dan murid membayar
        pekan yang ia dapat saja. Berlaku hanya untuk <strong>invoice pertama</strong> —
        pekan mulai dipilih di data murid, dan bulan-bulan berikutnya selalu Biaya Kelas penuh.
    </p>
    <div class="row g-2" id="weekLadder">
        @foreach(\App\Models\ClassRoom::START_WEEKS as $week)
            @php $sisa = \App\Models\ClassRoom::WEEKS_PER_MONTH - $week + 1; @endphp
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 h-100">
                    <div class="small text-muted">Masuk Minggu ke-{{ $week }}</div>
                    <div class="fw-bold" data-week-fee="{{ $week }}">Rp 0</div>
                    <div class="small text-muted">{{ $sisa }} dari {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }} pekan</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dateInput = document.getElementById('schedule_date');
    const classType = document.getElementById('class_type');
    const dateHint = document.getElementById('scheduleDateHint');
    const recurringHint = document.getElementById('recurringHint');
    const recurringPattern = document.getElementById('recurringPattern');
    const startTime = document.getElementById('schedule_time');
    const endTime = document.getElementById('schedule_end_time');
    const durationHint = document.getElementById('durationHint');
    const classFee = document.getElementById('class_fee');
    const registrationFee = document.getElementById('registration_fee');
    const totalPreview = document.getElementById('totalFeePreview');

    const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    function hariTerpilih() {
        const p = (dateInput.value || '').split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);

        return isNaN(d.getTime()) ? '' : DAY_NAMES[d.getDay()];
    }

    // Hari kelas tidak diisi admin — diturunkan dari tanggal. Ditampilkan di sini
    // supaya admin tahu hari apa yang sebenarnya ia pilih, dan apa akibat tipe
    // kelas yang dipilihnya terhadap pengulangan.
    function updateHints() {
        const hari = hariTerpilih();
        const trial = classType && classType.value === 'trial';

        if (dateHint) {
            dateHint.innerHTML = hari
                ? '<i class="bi bi-calendar-event me-1"></i>Tanggal ini jatuh pada hari <strong>' + hari + '</strong>.'
                : '<i class="bi bi-calendar-event me-1"></i>Hari kelas diambil dari tanggal ini.';
        }

        // Kotaknya menyebut polanya sesingkat mungkin; alasan & akibatnya turun
        // ke keterangan di bawah, supaya tinggi barisnya tetap sama dengan
        // Tipe Kelas & Kapasitas di sebelahnya.
        if (recurringPattern) {
            recurringPattern.textContent = trial
                ? 'Sekali pertemuan'
                : (hari ? 'Berulang tiap ' + hari : 'Berulang tiap pekan');
        }

        if (recurringHint) {
            if (trial) {
                recurringHint.innerHTML = '<i class="bi bi-calendar-x me-1"></i>Berjalan sekali pada Tanggal Kelas, lalu ditandai sudah lewat.';
            } else {
                recurringHint.innerHTML = hari
                    ? '<i class="bi bi-arrow-repeat me-1"></i>Berulang <strong>tiap ' + hari + '</strong> sejak Tanggal Kelas, sampai statusnya ditutup.'
                    : '<i class="bi bi-arrow-repeat me-1"></i>Berulang tiap pekan sejak Tanggal Kelas.';
            }
        }
    }

    // Total bayar awal dihitung di layar supaya admin tidak perlu menjumlah
    // sendiri sebelum menyebutkan angkanya ke orang tua murid.
    function updateTotal() {
        if (!totalPreview) return;
        const total = (parseFloat(classFee.value) || 0) + (parseFloat(registrationFee.value) || 0);
        totalPreview.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    // Tangga harga mengikuti Biaya Kelas seketika. Rumusnya disalin dari
    // ClassRoom::feeForStartWeek() — ini hanya pratinjau; yang dipakai saat
    // menagih tetap hitungan di server.
    const PEKAN_SEBULAN = {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }};

    function updateWeekLadder() {
        const penuh = parseFloat(classFee.value) || 0;
        document.querySelectorAll('[data-week-fee]').forEach(function (box) {
            const sisa = PEKAN_SEBULAN - Number(box.dataset.weekFee) + 1;
            box.textContent = 'Rp ' + Math.round(penuh / PEKAN_SEBULAN * sisa).toLocaleString('id-ID');
        });
    }

    const menitDari = (nilai) => {
        const [j, m] = (nilai || '').split(':').map(Number);

        return Number.isFinite(j) && Number.isFinite(m) ? j * 60 + m : null;
    };

    // Jam selesai diisikan satu jam setelah mulai — hanya bila masih kosong.
    // Menimpa isian yang sudah ada berarti mengubah jadwal di belakang admin,
    // dan kelas 90 menit di sanggar ini bukan hal aneh.
    function suggestEnd() {
        if (!endTime || endTime.value) return;

        const mulai = menitDari(startTime.value);
        if (mulai === null) return;

        const selesai = (mulai + 60) % (24 * 60);
        endTime.value = String(Math.floor(selesai / 60)).padStart(2, '0') + ':' + String(selesai % 60).padStart(2, '0');
        updateDuration();
    }

    // Lamanya sesi disebutkan langsung: "12:30" di sebelah "11:00" tidak
    // menjawab "ini kelas berapa lama" tanpa admin menghitung sendiri.
    function updateDuration() {
        if (!durationHint) return;

        const mulai = menitDari(startTime.value);
        const selesai = menitDari(endTime.value);

        if (mulai === null || selesai === null) {
            durationHint.textContent = '';

            return;
        }

        const lama = selesai - mulai;

        if (lama <= 0) {
            durationHint.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Harus setelah jam mulai.</span>';

            return;
        }

        const jam = Math.floor(lama / 60);
        const menit = lama % 60;
        durationHint.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Lama sesi ' +
            (jam ? jam + ' jam ' : '') + (menit ? menit + ' menit' : '');
    }

    if (startTime) {
        startTime.addEventListener('input', function () { suggestEnd(); updateDuration(); });
    }
    if (endTime) endTime.addEventListener('input', updateDuration);
    if (dateInput) dateInput.addEventListener('input', updateHints);
    if (classType) classType.addEventListener('change', updateHints);
    if (classFee) classFee.addEventListener('input', function () { updateTotal(); updateWeekLadder(); });
    if (registrationFee) registrationFee.addEventListener('input', updateTotal);

    updateHints();
    updateTotal();
    updateWeekLadder();
    updateDuration();
});
</script>
@endpush
