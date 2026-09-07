{{-- Blok 1 — Identitas kelas: apa yang diajarkan, siapa tutornya, dan muat
     berapa anak. Empat isian, dua kolom, dua baris. --}}
<h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-easel2 me-1"></i>Informasi kelas</h6>
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
                <option value="">— Pilih tutor —</option>
                @foreach($tutors as $tutor)
                    <option value="{{ $tutor->id }}" @selected(old('tutor_id', $class->tutor_id ?? '') == $tutor->id)>{{ $tutor->name }}</option>
                @endforeach
            </select>
            @error('tutor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        {{-- Tipe kelas menggantikan saklar pengulangan: trial hanya sekali pertemuan,
             reguler berjalan tiap pekan. Controller yang menurunkan is_recurring. --}}
        <label class="form-label fw-semibold">Tipe kelas <span class="text-danger">*</span></label>
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
    <div class="col-md-6">
        <label class="form-label fw-semibold">Kapasitas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-people"></i></span>
            <input type="number" name="capacity" min="1" class="form-control @error('capacity') is-invalid @enderror" value="{{ old('capacity', $class->capacity ?? '') }}" placeholder="0" required>
            <span class="input-group-text">murid</span>
            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<hr class="my-4">

{{-- Blok 2 — Jadwal: kapan kelas berjalan. Tiga isian sejajar — tanggal, jam
     mulai, jam selesai.

     Hari kelas tidak diisi admin, diturunkan dari tanggalnya; polanya pun tidak,
     karena sudah ditentukan Tipe kelas (trial sekali jalan, reguler mingguan).
     Keduanya tak ditampilkan lagi di sini — barisnya dijaga tetap ringkas. --}}
<h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-calendar-week me-1"></i>Jadwal</h6>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label fw-semibold">Tanggal kelas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-calendar-event"></i></span>
            <input type="date" name="schedule_date" id="schedule_date" class="form-control @error('schedule_date') is-invalid @enderror"
                value="{{ old('schedule_date', isset($class) ? $class->schedule_date->format('Y-m-d') : now()->toDateString()) }}" required>
            @error('schedule_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    {{-- Jam mulai & selesai diketik apa adanya.

         Sanggar memang berjalan dengan irama 1,5 jam mulai 09:00, tapi itu
         kebiasaan mayoritas, bukan hukum: tiap kategori punya aturan jam & hari
         sendiri, dan Preschool cuma Senin 16:00–17:00. Kotak jam yang menawarkan
         pilihan tertutup — enam slot, durasi tetap — membuat jadwal seperti itu
         mustahil dicatat, dan jadwal yang tak bisa dicatat akan hidup di kepala
         orang, bukan di sistem.

         Jam selesai mengikuti jam mulai — 1,5 jam sesudahnya, panjang sesi
         kebanyakan kelas — tapi tetap kotak biasa yang boleh diketik ulang.
         Kelas 60 menit cukup diperbaiki angkanya, dan angka itu bertahan sampai
         jam mulainya sendiri diubah. --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="schedule_time">Jam mulai <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-clock"></i></span>
            <input type="time" name="schedule_time" id="schedule_time" class="form-control @error('schedule_time') is-invalid @enderror"
                value="{{ old('schedule_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_time)->substr(0, 5) : \App\Models\ClassRoom::SLOT_START) }}" required>
            @error('schedule_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold" for="schedule_end_time">Jam selesai <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-clock-history"></i></span>
            <input type="time" name="schedule_end_time" id="schedule_end_time" class="form-control @error('schedule_end_time') is-invalid @enderror"
                value="{{ old('schedule_end_time', isset($class) ? \Illuminate\Support\Str::of($class->schedule_end_time)->substr(0, 5) : '') }}" required>
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
        <label class="form-label fw-semibold">Biaya kelas <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted">Rp</span>
            <input type="number" step="1000" min="0" name="class_fee" id="class_fee" class="form-control @error('class_fee') is-invalid @enderror" value="{{ old('class_fee', isset($class) ? (int) $class->class_fee : '') }}" placeholder="0" required>
            @error('class_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1">Iuran per bulan, ditagih penuh mulai bulan kedua.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Uang pendaftaran <span class="badge bg-body-secondary text-body-secondary border fw-semibold ms-1">Opsional</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted">Rp</span>
            <input type="number" step="1000" min="0" name="registration_fee" id="registration_fee" class="form-control @error('registration_fee') is-invalid @enderror" value="{{ old('registration_fee', isset($class) ? (int) $class->registration_fee : '') }}" placeholder="0">
            @error('registration_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <small class="text-muted d-block mt-1">Kosongkan bila kelas ini tidak memungutnya.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label fw-semibold">Total bayar awal</label>
        <div class="input-group">
            <span class="input-group-text bg-light text-muted"><i class="bi bi-calculator"></i></span>
            <div class="form-control bg-body-secondary fw-bold" id="totalFeePreview">Rp 0</div>
        </div>
        <small class="text-muted d-block mt-1">Biaya kelas + uang pendaftaran.</small>
    </div>
</div>

{{-- Harga bulan pertama tidak diketik, melainkan diturunkan dari Biaya kelas:
     murid membayar pekan yang benar-benar ia dapat. Ditampilkan di sini supaya
     admin melihat akibat angka yang baru saja ia ketik, bukan menghitung sendiri
     saat orang tua bertanya. --}}
<div class="mt-4">
    <label class="form-label mb-1">Harga bulan pertama menurut pekan murid masuk</label>
    <p class="text-muted small mb-3">
        Dihitung otomatis: sebulan {{ \App\Models\ClassRoom::WEEKS_PER_MONTH }} pekan, dan murid membayar
        pekan yang ia dapat saja. Berlaku hanya untuk <strong>invoice pertama</strong> —
        pekan mulai dipilih di data murid, dan bulan-bulan berikutnya selalu Biaya kelas penuh.
    </p>
    <div class="row g-2" id="weekLadder">
        @foreach(\App\Models\ClassRoom::START_WEEKS as $week)
            @php $sisa = \App\Models\ClassRoom::WEEKS_PER_MONTH - $week + 1; @endphp
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 h-100">
                    <div class="small text-muted">Masuk minggu ke-{{ $week }}</div>
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
    const startTime = document.getElementById('schedule_time');
    const endTime = document.getElementById('schedule_end_time');
    const durationHint = document.getElementById('durationHint');
    const classFee = document.getElementById('class_fee');
    const registrationFee = document.getElementById('registration_fee');
    const totalPreview = document.getElementById('totalFeePreview');

    // Total bayar awal dihitung di layar supaya admin tidak perlu menjumlah
    // sendiri sebelum menyebutkan angkanya ke orang tua murid.
    function updateTotal() {
        if (!totalPreview) return;
        const total = (parseFloat(classFee.value) || 0) + (parseFloat(registrationFee.value) || 0);
        totalPreview.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    // Tangga harga mengikuti Biaya kelas seketika. Rumusnya disalin dari
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

    // Panjang sesi bawaan sanggar. Bukan aturan: kelas 60 menit maupun 2 jam
    // sama sahnya, admin tinggal mengubah angka jam selesainya.
    const SLOT_MENIT = {{ \App\Models\ClassRoom::SLOT_MINUTES }};

    /**
     * Isi jam selesai 1,5 jam setelah jam mulai.
     *
     * `paksa` memisahkan dua keadaan yang tampak mirip tapi berlawanan:
     *
     *  - Saat admin mengetik jam mulai (paksa), jam selesai ikut bergeser. Itu
     *    yang diharapkan: mengubah jam kelas berarti mengubah seluruh sesinya,
     *    dan angka lama yang tertinggal justru jadi jadwal yang tak pernah
     *    dimaksud siapa pun.
     *  - Saat form baru dibuka (tanpa paksa), yang sudah tertulis dibiarkan.
     *    Kelas Preschool 16:00-17:00 yang dibuka untuk sekadar ganti tutor tidak
     *    boleh diam-diam berubah jadi 17:30.
     */
    function ikutiJamMulai(paksa) {
        if (!endTime || (!paksa && endTime.value)) return;

        const mulai = menitDari(startTime.value);
        if (mulai === null) return;

        const selesai = (mulai + SLOT_MENIT) % (24 * 60);
        endTime.value = String(Math.floor(selesai / 60)).padStart(2, '0') + ':' + String(selesai % 60).padStart(2, '0');
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
        startTime.addEventListener('input', function () { ikutiJamMulai(true); updateDuration(); });
    }
    if (endTime) endTime.addEventListener('input', updateDuration);
    if (classFee) classFee.addEventListener('input', function () { updateTotal(); updateWeekLadder(); });
    if (registrationFee) registrationFee.addEventListener('input', updateTotal);

    updateTotal();
    updateWeekLadder();
    // Kelas baru & kelas lama yang jam selesainya belum pernah diisi mendapat
    // isiannya begitu form dibuka, bukan menunggu admin menyentuh jam mulai.
    ikutiJamMulai(false);
    updateDuration();
});
</script>
@endpush
