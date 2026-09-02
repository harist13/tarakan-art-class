@php
    // Kelas asal yang sedang terpilih — dipakai merender daftar sesinya saat load,
    // sebelum JS mengambil alih.
    $originId = old('origin_class_id', $request->origin_class_id ?? request('origin_class_id', ''));
    $originClass = $originId ? $classes->firstWhere('id', (int) $originId) : null;
    $availableCount = $classes->filter->isAvailable()->count();
@endphp

{{-- Form disusun mengikuti urutan berpikir admin: siapa muridnya, sesi mana yang
     dilewatkan, lalu diganti kapan. Penomorannya bukan hiasan — tanpa itu "kelas
     asal" dan "kelas pengganti" mudah tertukar, karena keduanya dropdown kelas
     yang isinya nyaris sama. --}}

{{-- ── 1. Murid ──────────────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge rounded-pill text-bg-primary">1</span>
    <span class="fw-semibold">Murid yang mengajukan</span>
</div>
<div class="row mb-4">
    <div class="col-md-6">
        <label class="form-label" for="student_id">Murid</label>
        <select name="student_id" id="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                @php $enrolled = $student->relationLoaded('classes') ? $student->classes->pluck('id') : collect(); @endphp
                <option value="{{ $student->id }}" data-name="{{ $student->name }}" data-category="{{ $student->class_type }}" data-origin="{{ $enrolled->first() }}" data-enrolled="{{ $enrolled->join(',') }}" @selected(old('student_id', $request->student_id ?? request('student_id', '')) == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
        @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1">
            <i class="bi bi-cash-coin me-1"></i>Murid yang invoice-nya lewat jatuh tempo tidak muncul di daftar ini.
            @if($students->isEmpty())
                <span class="text-danger d-block">Semua murid sedang menunggak — lunasi dulu di <a href="{{ route('payments.index') }}">menu Pembayaran</a>.</span>
            @endif
        </small>
    </div>
</div>

{{-- ── 2. Sesi yang dilewatkan ───────────────────────────────────────────── --}}
<div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge rounded-pill text-bg-primary">2</span>
    <span class="fw-semibold">Sesi yang dilewatkan</span>
</div>
<div class="row mb-4">
    <div class="col-md-6 mb-3 mb-md-0">
        <label class="form-label" for="origin_class_id">Kelas asal</label>
        <select name="origin_class_id" id="origin_class_id" class="form-select @error('origin_class_id') is-invalid @enderror">
            <option value="">— Pilih Kelas Asal —</option>
            @foreach($classes as $class)
                {{-- data-schedule: sesi bertanggal untuk kotak "Sesi terdekat".
                     data-next + data-time: tanggal & jam sesi yang ditinggalkan,
                     dipakai mendeteksi sesi pengganti yang jatuh tepat di situ.
                     data-next kosong juga menandai kelas tanpa sesi mendatang —
                     sessionLabel() tetap terisi untuk kelas sekali jalan yang sudah lewat. --}}
                {{-- data-sessions: sesi nyata kelas ini di sekitar hari ini, sumber
                     pilihan "tanggal sesi yang dilewatkan". Dibatasi ke sesi yang
                     benar-benar ada karena tanggal lain tak pernah cocok dengan
                     absensi — muridnya tak akan dikeluarkan dari sesi mana pun. --}}
                <option value="{{ $class->id }}" data-name="{{ $class->class_category }}"
                    data-category="{{ $class->class_category }}"
                    data-time="{{ $class->timeLabel() }}"
                    data-schedule="{{ $class->sessionLabel() }}"
                    data-next="{{ $class->nextOccurrence()?->format('Y-m-d') }}"
                    data-sessions="{{ collect($class->sessionWindow())->map->format('Y-m-d')->join(',') }}"
                    @selected(old('origin_class_id', $request->origin_class_id ?? request('origin_class_id', '')) == $class->id)>
                    {{ $class->class_category }} ({{ $class->class_code }}) · {{ $class->availability()['text'] }}
                </option>
            @endforeach
        </select>
        @error('origin_class_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        {{-- Peringatan saat murid belum punya enrollment aktif — kelas asal harus dipilih manual. --}}
        <div class="small text-danger mt-1 d-none" id="noClassWarning"><i class="bi bi-exclamation-triangle me-1"></i>Murid ini belum memilih kelas.</div>
        <small class="text-muted d-block mt-1" id="originHint"><i class="bi bi-box-arrow-left me-1"></i>Terisi otomatis dari kelas aktif murid.</small>
    </div>
    {{-- Tanggal sesi yang ditinggalkan. Daftar sesi kelas asal, bukan pemilih
         tanggal bebas: nilainya harus sesi yang benar-benar ada, karena absensi
         mencocokkannya persis untuk tahu murid mana yang tidak perlu diabsen.

         Terpilih otomatis pada sesi terdekat, tapi tetap bisa diganti — kelas
         pengganti sering diminta setelah anaknya absen, jadi yang dilewatkan bisa
         sesi pekan lalu.

         Opsinya dirender server lebih dulu agar mode edit & old input tetap benar;
         JS menyusunnya ulang tiap kelas asal berganti. data-no-search menjaganya
         tetap select biasa supaya aman disusun ulang. --}}
    @php
        $missedDate = old('missed_date', isset($request) ? $request->missed_date?->format('Y-m-d') : request('missed_date', ''));
        $originSessions = $originClass ? collect($originClass->sessionWindow()) : collect();
        // Nilai tersimpan di luar rentang tetap ditawarkan: form edit tidak boleh
        // diam-diam menggeser tanggal yang sudah pernah disetujui.
        $missedDiLuarRentang = $missedDate && $originClass
            && ! $originSessions->contains(fn ($at) => $at->format('Y-m-d') === $missedDate);
    @endphp
    <div class="col-md-6">
        <label class="form-label" for="missed_date">Tanggal sesi yang dilewatkan</label>
        <select name="missed_date" id="missed_date" class="form-select @error('missed_date') is-invalid @enderror" data-no-search>
            @if($originSessions->isEmpty() && ! $missedDiLuarRentang)
                <option value="">{{ $originClass ? '— Kelas asal tidak punya sesi di rentang ini —' : '— Pilih kelas asal dulu —' }}</option>
            @endif
            @if($missedDiLuarRentang)
                <option value="{{ $missedDate }}" selected>{{ \App\Models\ClassRoom::formatSession($originClass->occurrenceAt(\Illuminate\Support\Carbon::parse($missedDate))) }} · di luar rentang</option>
            @endif
            @foreach($originSessions as $at)
                <option value="{{ $at->format('Y-m-d') }}" @selected($missedDate === $at->format('Y-m-d'))>{{ \App\Models\ClassRoom::formatSession($at) }}</option>
            @endforeach
        </select>
        @error('missed_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="d-block mt-1" id="currentScheduleHint"></small>
    </div>
</div>

{{-- ── 3. Sesi pengganti ─────────────────────────────────────────────────── --}}
<div class="d-flex align-items-center gap-2 mb-3">
    <span class="badge rounded-pill text-bg-primary">3</span>
    <span class="fw-semibold">Sesi penggantinya</span>
</div>
<div class="row mb-4">
    {{-- Peringatan + panduan saat tak ada slot tersedia sama sekali; submit dinonaktifkan agar tak gagal di server. --}}
    <div class="col-12 mb-3 d-none" id="noSlotAlert">
        <div class="alert alert-warning mb-0">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Tidak ada slot pengganti yang tersedia</div>
            <p class="small mb-2">Semua slot sedang penuh, ditutup, sudah lewat jadwalnya, jatuh di hari libur, atau tutornya belum tersedia.</p>
            <div class="small mb-0">Yang bisa dilakukan:
                <ul class="mb-0 ps-3">
                    <li>Buka kembali slot yang ditutup lewat <a href="{{ route('schedules.index') }}" class="alert-link">Pengaturan Jadwal &amp; Slot</a>.</li>
                    <li>Tambah kelas baru dengan jadwal mendatang di <a href="{{ route('classes.index') }}" class="alert-link">Manajemen Kelas</a>.</li>
                    <li>Pastikan tutor kelas tersebut berstatus aktif.</li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="class_id">Kelas pengganti</label>
        <select name="class_id" id="class_id" class="form-select" required>
            <option value="">— Pilih Kelas —</option>
            @foreach($classes as $class)
                @php $selected = old('class_id', $request->class_id ?? request('class_id', '')) == $class->id; @endphp
                {{-- Hanya tampilkan slot yang tersedia; kelas yang sedang dipilih (saat edit) tetap muncul. --}}
                @if($class->isAvailable() || $selected)
                    {{-- data-date/data-time: sumber isian otomatis tanggal & jam pengganti.
                         Tanggalnya sesi mingguan berikutnya, bukan tanggal mulai berlaku slot.
                         data-date-after: sesi sesudahnya, dipakai saat kelas pengganti sama
                         dengan kelas asal — sesi terdekatnya justru yang ditinggalkan. --}}
                    @php
                        $nextSession = $class->nextOccurrence();
                        $sessionAfter = $nextSession ? $class->nextOccurrence($nextSession->copy()->addSecond()) : null;
                    @endphp
                    <option value="{{ $class->id }}" data-name="{{ $class->class_category }}"
                        data-category="{{ $class->class_category }}"
                        data-date="{{ $nextSession?->format('Y-m-d') }}"
                        data-date-after="{{ $sessionAfter?->format('Y-m-d') }}"
                        data-time="{{ $class->timeLabel() }}" @selected($selected)>
                        {{ $class->class_category }} ({{ $class->class_code }}) · {{ $class->availability()['text'] }}
                    </option>
                @endif
            @endforeach
        </select>
        <small class="text-muted d-block mt-1" id="classHint">
            @if($availableCount === 0)
                <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak ada slot yang tersedia saat ini.</span>
            @else
                <i class="bi bi-funnel me-1"></i>Pilih murid dulu untuk melihat slot mana yang setipe dengannya.
            @endif
        </small>
    </div>
    {{-- Tanggal & jam pengganti terisi otomatis dari jadwal kelas pengganti, tapi
         tetap bisa ditimpa admin bila sesinya digeser. Dikosongkan = ikut jadwal
         kelas (diisi ulang di server), jadi keduanya sengaja tidak `required`. --}}
    <div class="col-md-3 mb-3">
        <label class="form-label" for="replacement_date">Tanggal</label>
        <input type="date" name="replacement_date" id="replacement_date" class="form-control @error('replacement_date') is-invalid @enderror" value="{{ old('replacement_date', isset($request) ? $request->replacement_date->format('Y-m-d') : request('replacement_date', '')) }}">
        @error('replacement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 mb-3">
        <label class="form-label" for="replacement_time">Jam</label>
        <input type="time" name="replacement_time" id="replacement_time" class="form-control @error('replacement_time') is-invalid @enderror" value="{{ old('replacement_time', isset($request) ? \Illuminate\Support\Str::of($request->replacement_time)->substr(0,5) : request('replacement_time', '')) }}">
        @error('replacement_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <span class="small text-muted" id="scheduleSyncHint">
            <i class="bi bi-calendar-event me-1"></i>Pilih kelas pengganti — tanggal &amp; jam akan terisi otomatis dari jadwalnya.
        </span>
    </div>
</div>

{{-- Ringkasan pemeriksaan akhir: lima isian di atas dirangkum jadi satu baris
     "dari sesi ini → ke sesi itu". Ini satu-satunya tempat admin bisa memastikan
     sesi mana yang benar-benar berpindah sebelum mengajukan. --}}
<div class="border rounded p-3 mb-4 bg-light d-none" id="replacementSummary">
    <div class="row g-3 align-items-center">
        <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-semibold mb-1">Murid</div>
            <div class="fw-semibold" id="summaryStudent">—</div>
        </div>
        <div class="col-md-4">
            <div class="small text-muted text-uppercase fw-semibold mb-1"><i class="bi bi-box-arrow-left me-1"></i>Dilewatkan</div>
            <div class="fw-semibold" id="summaryFromClass">—</div>
            <div class="small text-muted" id="summaryFromSession">—</div>
        </div>
        <div class="col-md-4">
            <div class="small text-success text-uppercase fw-semibold mb-1"><i class="bi bi-box-arrow-in-right me-1"></i>Diganti ke</div>
            <div class="fw-semibold" id="summaryToClass">—</div>
            <div class="small text-muted" id="summaryToSession">—</div>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="reason">Alasan <span class="text-muted">(opsional)</span></label>
    <textarea name="reason" id="reason" class="form-control" rows="2" placeholder="mis. sakit, ada acara keluarga — dibaca Super Admin saat menyetujui">{{ old('reason', $request->reason ?? '') }}</textarea>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const studentSel = document.getElementById('student_id');
    const classSel = document.getElementById('class_id');
    const originSel = document.getElementById('origin_class_id');
    const hint = document.getElementById('classHint');
    const originHint = document.getElementById('originHint');
    const noClassWarning = document.getElementById('noClassWarning');
    const noSlotAlert = document.getElementById('noSlotAlert');
    const dateInput = document.getElementById('replacement_date');
    const timeInput = document.getElementById('replacement_time');
    const syncHint = document.getElementById('scheduleSyncHint');
    const missedSel = document.getElementById('missed_date');
    const currentScheduleHint = document.getElementById('currentScheduleHint');
    const summary = document.getElementById('replacementSummary');
    if (!studentSel || !classSel) return;

    const form = classSel.form;
    const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

    // Dua hal berbeda bisa menghalangi submit, jadi statusnya dihitung di satu tempat.
    let noSlots = false;

    // Jadwal tiap kelas dipetakan sekali dari DOM awal: renderOptions() menyusun
    // ulang opsi lewat Tom Select yang tidak membawa data-attribute, jadi jadwalnya
    // tak bisa dibaca lagi dari option setelah penyaringan pertama. Harus dikumpulkan
    // sebelum penyaringan apa pun berjalan.
    //
    // Dikumpulkan dari kedua select: dropdown kelas asal memuat semua kelas,
    // sedangkan dropdown kelas tujuan hanya yang available tapi membawa tanggal
    // sesi berikutnya.
    const classSchedules = {};
    (function collectSchedules() {
        [originSel, classSel].forEach(function (sel) {
            if (!sel) return;
            Array.from(sel.options).forEach(function (o) {
                if (!o.value) return;
                const prev = classSchedules[o.value] || {};
                classSchedules[o.value] = {
                    name: o.dataset.name || prev.name || '',
                    date: o.dataset.date || prev.date || '',
                    dateAfter: o.dataset.dateAfter || prev.dateAfter || '',
                    time: o.dataset.time || prev.time || '',
                    // Hanya dropdown kelas asal yang membawa keduanya: label sesi
                    // terdekat + tanggalnya.
                    label: o.dataset.schedule || prev.label || '',
                    next: o.dataset.next || prev.next || '',
                    sessions: o.dataset.sessions || prev.sessions || '',
                };
            });
        });
    })();

    // Simpan daftar opsi asli tiap dropdown; di-render ulang dari sini saat menyaring.
    function snapshot(sel) {
        if (!sel) return [];
        return Array.from(sel.options)
            .filter(function (o) { return o.value; })
            .map(function (o) {
                return { value: o.value, text: o.textContent.trim().replace(/\s+/g, ' '), cat: o.dataset.category };
            });
    }

    const allClasses = snapshot(classSel);
    const allOrigins = snapshot(originSel);

    // Render ulang opsi sebuah select dari daftar yang lolos saringan, lalu kembalikan
    // nilai yang dipertahankan. Select di aplikasi ini di-upgrade jadi Tom Select,
    // jadi pakai API-nya bila tersedia; kalau tidak, sembunyikan opsi yang tak lolos.
    function renderOptions(sel, allowed, placeholder) {
        const previous = sel.value;
        const keep = allowed.some(function (c) { return c.value === previous; }) ? previous : '';
        const ts = sel.tomselect;

        if (ts) {
            ts.clearOptions(function () { return false; }); // buang semua, termasuk yang terpilih
            ts.addOption({ value: '', text: placeholder });
            allowed.forEach(function (c) { ts.addOption({ value: c.value, text: c.text }); });
            ts.refreshOptions(false);
            ts.setValue(keep, true); // silent: jangan picu event change
        } else {
            const ids = allowed.map(function (c) { return c.value; });
            Array.from(sel.options).forEach(function (o) {
                if (!o.value) return; // biarkan placeholder
                const ok = ids.indexOf(o.value) !== -1;
                o.hidden = !ok;
                o.disabled = !ok;
            });
            // Susun ulang mengikuti urutan `allowed` (appendChild memindahkan node yang ada).
            allowed.forEach(function (c) {
                const o = sel.querySelector('option[value="' + c.value + '"]');
                if (o) sel.appendChild(o);
            });
            sel.value = keep;
        }

        return keep;
    }

    // Semua slot tersedia ditampilkan — murid boleh replacement lintas tipe kelas.
    // Slot yang setipe dengan murid diurutkan di atas sebagai saran utama.
    function filterClasses() {
        const opt = studentSel.selectedOptions[0];
        const cat = opt ? opt.dataset.category : '';
        const sameType = cat ? allClasses.filter(function (c) { return c.cat === cat; }) : [];
        const otherType = allClasses.filter(function (c) { return !cat || c.cat !== cat; });
        const allowed = sameType.concat(otherType);
        const total = allowed.length;

        renderOptions(classSel, allowed, '— Pilih Kelas —');

        if (hint) {
            if (total === 0) {
                hint.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Tidak ada slot yang tersedia saat ini.</span>';
            } else if (!cat) {
                hint.innerHTML = '<i class="bi bi-funnel me-1"></i>Pilih murid dulu untuk melihat slot mana yang setipe dengannya.';
            } else if (sameType.length === 0) {
                hint.innerHTML = '<span class="text-warning-emphasis"><i class="bi bi-info-circle me-1"></i>Tidak ada slot ' + cat + '. ' + otherType.length + ' slot tipe lain tetap bisa dipilih.</span>';
            } else {
                hint.innerHTML = '<i class="bi bi-funnel me-1"></i>' + sameType.length + ' slot ' + cat + ' (di urutan atas) + ' + otherType.length + ' slot tipe lain.';
            }
        }

        // Terhalang hanya bila memang tak ada slot sama sekali — bukan karena beda tipe.
        noSlots = total === 0;
        if (noSlotAlert) {
            noSlotAlert.classList.toggle('d-none', !noSlots);
        }
        updateSubmitState();
        // Kelas wajib diisi hanya bila ada pilihan — hindari pesan "required" yang membingungkan.
        classSel.required = !noSlots;
    }

    function updateSubmitState() {
        if (!submitBtn) return;
        const conflict = sameAsOrigin();
        submitBtn.disabled = noSlots || conflict;
        submitBtn.title = noSlots
            ? 'Tidak ada slot pengganti yang tersedia'
            : (conflict ? 'Sesi pengganti jatuh tepat pada sesi yang ditinggalkan' : '');
    }

    // ── Kelas asal: kelas yang diikuti murid diutamakan, lalu terisi otomatis ──

    /** Id kelas yang benar-benar diikuti murid (enrollment aktif). */
    function enrolledIds() {
        const opt = studentSel.selectedOptions[0];
        const raw = opt ? (opt.dataset.enrolled || '') : '';
        return raw ? raw.split(',') : [];
    }

    // Semua kelas tetap bisa dipilih (murid bisa lintas tipe); kelas yang memang
    // diikuti murid dinaikkan ke urutan atas karena itu yang paling mungkin benar.
    function filterOrigins() {
        if (!originSel) return;
        const mine = enrolledIds();
        const isMine = function (c) { return mine.indexOf(c.value) !== -1; };
        const allowed = allOrigins.filter(isMine).concat(allOrigins.filter(function (c) { return !isMine(c); }));
        renderOptions(originSel, allowed, '— Pilih Kelas Asal —');

        // Murid tanpa enrollment aktif: beri tahu admin, kelas asal harus dipilih manual.
        const noClass = !!studentSel.value && mine.length === 0;
        if (noClassWarning) {
            noClassWarning.classList.toggle('d-none', !noClass);
        }

        if (originHint) {
            originHint.innerHTML = mine.length
                ? '<i class="bi bi-box-arrow-left me-1"></i>Kelas aktif murid ada di urutan atas & terisi otomatis. Kelas lain tetap bisa dipilih.'
                : (noClass
                    ? '<i class="bi bi-box-arrow-left me-1"></i>Pilih sendiri jadwal yang ditinggalkan, atau kosongkan bila memang belum ada.'
                    : '<i class="bi bi-box-arrow-left me-1"></i>Jadwal yang ditinggalkan murid. Terisi otomatis dari kelas aktifnya.');
        }
    }

    // Set nilai select; pakai API Tom Select bila select sudah di-upgrade.
    function setSelectValue(sel, value) {
        if (sel.tomselect) sel.tomselect.setValue(value, true); // silent
        else sel.value = value;
    }

    // force = true saat murid berganti (timpa pilihan lama),
    // false saat load agar nilai mode edit / old input tetap dihormati.
    function fillOrigin(force) {
        if (!originSel) return;
        if (!force && originSel.value) return;
        const opt = studentSel.selectedOptions[0];
        const origin = opt ? (opt.dataset.origin || '') : '';
        if (origin || force) setSelectValue(originSel, origin);
    }

    // Sekali admin memilih kelas baru sendiri, pilihannya berhenti dicerminkan
    // dari kelas asal. Direset saat murid berganti — di situ seluruh isian form
    // memang diturunkan ulang.
    let classManual = false;
    classSel.addEventListener('change', function () { classManual = true; });

    // Kelas baru mengikuti kelas asal: kasus paling umum adalah menyusul sesi di
    // kelas yang sama, jadi itu dijadikan titik awal — admin tinggal memilih kelas
    // lain bila memang pindah kelas.
    //
    // Hanya dicerminkan bila kelas asal memang muncul sebagai slot tujuan:
    // dropdown kelas baru hanya memuat slot available, jadi kelas asal yang
    // penuh/ditutup tidak ada padanannya di sana. Pilihan yang sudah ada
    // dibiarkan daripada dikosongkan.
    //
    // force = true saat admin/murid mengubah pilihan, false saat load agar
    // nilai mode edit / old input tetap dihormati.
    function mirrorOriginToClass(force) {
        if (!originSel || !originSel.value || classManual) return;
        if (!force && classSel.value) return;

        const adaSlotnya = allClasses.some(function (c) { return c.value === originSel.value; });
        if (adaSlotnya) setSelectValue(classSel, originSel.value);
    }

    // Susun ulang daftar sesi kelas asal. Dipanggil hanya saat kelas asalnya
    // benar-benar berganti — di luar itu pilihan admin tidak boleh diganggu.
    function fillMissedOptions() {
        if (!missedSel) return;
        const s = originSel ? classSchedules[originSel.value] : null;
        const list = (s && s.sessions) ? s.sessions.split(',').filter(Boolean) : [];

        missedSel.innerHTML = '';

        if (!list.length) {
            missedSel.appendChild(new Option(
                s ? '— Kelas asal tidak punya sesi di rentang ini —' : '— Pilih kelas asal dulu —', ''
            ));

            return;
        }

        list.forEach(function (iso) {
            missedSel.appendChild(new Option(sessionText(iso, s.time), iso));
        });
        // Sesi terdekat jadi pilihan awal; itu kasus yang paling sering benar.
        missedSel.value = (s.next && list.indexOf(s.next) !== -1) ? s.next : list[0];
    }

    if (missedSel) {
        missedSel.addEventListener('change', function () {
            updateCurrentSchedule();
            // Menggeser sesi yang dilewatkan bisa memunculkan/menghilangkan konflik.
            if (dateInput && timeInput) updateSyncHint();
        });
    }

    // Terangkan hubungan sesi yang dipilih dengan sesi terdekat kelas asal.
    function updateCurrentSchedule() {
        if (!currentScheduleHint) return;
        const s = originSel ? classSchedules[originSel.value] : null;
        const dipilih = missedSel ? missedSel.value : '';

        currentScheduleHint.className = 'small d-block mt-1 text-muted';

        if (!s) {
            currentScheduleHint.innerHTML = '<i class="bi bi-magic me-1"></i>Daftar sesi muncul begitu kelas asal dipilih.';
        } else if (!dipilih) {
            currentScheduleHint.className = 'small d-block mt-1 text-warning-emphasis';
            currentScheduleHint.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Kelas asal tidak punya sesi di rentang ini.';
        } else if (dipilih !== s.next) {
            currentScheduleHint.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Dipilih sendiri. Sesi terdekat kelas asal: <strong>' + s.label + '</strong>.';
        } else {
            currentScheduleHint.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Sesi terdekat kelas asal, terpilih otomatis.';
        }
    }

    // ── Tanggal & jam pengganti mengikuti jadwal kelas pengganti ──

    // Sekali admin mengetik sendiri sebuah field, field itu berhenti ikut jadwal
    // kelas.
    let dateManual = false;
    let timeManual = false;

    // Jadwal kelas tujuan sebagaimana dipakai untuk mengisi tanggal & jam.
    // Saat kelas tujuan sama dengan kelas asal, sesi terdekatnya justru sesi yang
    // sedang ditinggalkan — yang ditawarkan sesi sesudahnya, supaya isian otomatis
    // tidak langsung jatuh di sesi yang ditolak.
    function classSchedule() {
        const s = classSchedules[classSel.value];
        if (!s) return null;

        const kelasSama = !!originSel && !!originSel.value && originSel.value === classSel.value;

        return (kelasSama && s.dateAfter) ? Object.assign({}, s, { date: s.dateAfter }) : s;
    }

    /**
     * "Senin, 17 Agustus 2026 - 10.00 AM" — bentuk yang sama persis dengan
     * ClassRoom::sessionLabel() di server, supaya sesi yang ditinggalkan dan sesi
     * penggantinya bisa dibandingkan sekilas tanpa menerjemahkan dua format.
     */
    function sessionText(iso, hhmm) {
        const p = String(iso).split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);
        const tanggal = isNaN(d.getTime())
            ? iso
            : d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

        if (!hhmm) return tanggal;

        // 24 jam → 12 jam: input type=time selalu memberi HH:MM.
        const jam = +String(hhmm).split(':')[0];
        const menit = String(hhmm).split(':')[1] || '00';
        const siang = jam >= 12 ? 'PM' : 'AM';

        return tanggal + ' - ' + ((jam % 12) || 12) + '.' + menit + ' ' + siang;
    }

    /** Jadwal kelas pengganti sebagai teks sesi. */
    function scheduleLabel(s) {
        return sessionText(s.date, s.time);
    }

    /**
     * Sesi pengganti jatuh tepat pada sesi yang ditinggalkan: kelas yang sama,
     * tanggal yang sama, jam yang sama. Tanggalnya dibandingkan persis — menyusul
     * di sesi minggu berikutnya pada kelas yang sama justru sah, jadi hari
     * mingguan yang sama saja bukan masalah.
     */
    function sameAsOrigin() {
        if (!originSel || !dateInput || !timeInput) return false;
        if (!originSel.value || originSel.value !== classSel.value) return false;
        if (!dateInput.value || !timeInput.value) return false;

        const s = classSchedules[originSel.value];
        const missed = missedDate();
        if (!s || !missed || !s.time) return false;

        return dateInput.value === missed && timeInput.value === s.time;
    }

    /** Tanggal sesi yang dilewatkan; jatuh kembali ke sesi terdekat kelas asal. */
    function missedDate() {
        const s = originSel ? classSchedules[originSel.value] : null;

        return (missedSel && missedSel.value) ? missedSel.value : (s ? s.next : '');
    }

    /** Isi teks sebuah elemen ringkasan, bila elemennya ada. */
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    // Ringkasan "dari → ke". Baru muncul setelah murid & kelas pengganti terisi,
    // karena sebelum itu belum ada yang bisa diperiksa.
    function updateSummary() {
        if (!summary) return;

        const tujuan = classSchedules[classSel.value];
        const lengkap = !!studentSel.value && !!tujuan;
        summary.classList.toggle('d-none', !lengkap);
        if (!lengkap) return;

        const murid = studentSel.selectedOptions[0];
        const asal = originSel ? classSchedules[originSel.value] : null;

        setText('summaryStudent', murid ? (murid.dataset.name || murid.textContent.trim()) : '—');
        const missed = missedDate();
        setText('summaryFromClass', asal ? asal.name : 'Tidak diisi');
        setText('summaryFromSession', asal
            ? (missed ? sessionText(missed, asal.time) : 'Tanggal sesi belum diisi')
            : '—');
        setText('summaryToClass', tujuan.name);
        setText('summaryToSession', dateInput && dateInput.value
            ? sessionText(dateInput.value, timeInput ? timeInput.value : '')
            : 'Mengikuti jadwal kelas');
    }

    function updateSyncHint() {
        const s = classSchedule();
        let cls = 'small text-muted';
        let html = '';

        if (sameAsOrigin()) {
            // Konflik dengan kelas asal diutamakan: ini satu-satunya kondisi yang
            // benar-benar menghalangi submit.
            cls = 'small text-danger';
            html = '<i class="bi bi-x-octagon me-1"></i>Sesi pengganti jatuh tepat pada sesi yang ditinggalkan (<strong>'
                + sessionText(missedDate(), classSchedules[originSel.value].time) + '</strong>) — tidak ada sesi yang berpindah. Pilih tanggal sesi lain, atau kelas pengganti yang berbeda.';
        } else if (!s) {
            html = '<i class="bi bi-calendar-event me-1"></i>Pilih kelas pengganti — tanggal &amp; jam akan terisi otomatis dari jadwalnya.';
        } else if (!dateInput.value || !timeInput.value) {
            html = '<i class="bi bi-calendar-event me-1"></i>Dikosongkan — akan mengikuti jadwal kelas pengganti (<strong>' + scheduleLabel(s) + '</strong>).';
        } else if (dateInput.value === s.date && timeInput.value === s.time) {
            html = '<i class="bi bi-calendar-check me-1"></i>Mengikuti jadwal kelas pengganti. Boleh diubah bila sesinya digeser.';
        }
        // Isian manual yang berbeda dari jadwal kelas memang diizinkan (sesi kadang
        // digeser), jadi kondisi itu sengaja dibiarkan tanpa catatan.

        if (syncHint) {
            syncHint.className = cls;
            syncHint.innerHTML = html;
        }

        updateSubmitState();
        updateSummary();
    }

    // Isi field yang masih "otomatis" dari jadwal kelas pengganti.
    function syncSchedule() {
        const s = classSchedule();
        if (s) {
            if (!dateManual && s.date) dateInput.value = s.date;
            if (!timeManual && s.time) timeInput.value = s.time;
        }
        updateSyncHint();
    }

    if (dateInput && timeInput) {
        // Nilai awal (mode edit / old input) dianggap manual hanya bila memang
        // berbeda dari jadwal kelas — supaya isian admin tidak ditimpa diam-diam.
        const awal = classSchedule();
        dateManual = !!dateInput.value && (!awal || dateInput.value !== awal.date);
        timeManual = !!timeInput.value && (!awal || timeInput.value !== awal.time);

        dateInput.addEventListener('input', function () { dateManual = true; updateSyncHint(); });
        timeInput.addEventListener('input', function () { timeManual = true; updateSyncHint(); });
        classSel.addEventListener('change', syncSchedule);
    }

    // Satu handler untuk kelas asal: isi field jadwal, cerminkan ke kelas baru,
    // lalu segarkan tanggal/jam & hint. syncSchedule() sekaligus menghitung ulang
    // konflik dengan kelas asal — mengganti kelas asal bisa memunculkan atau
    // menghilangkannya tanpa mengubah tanggal & jam sama sekali.
    if (originSel) {
        originSel.addEventListener('change', function () {
            // Kelas asal berganti berarti daftar sesinya ikut berganti — sesi lama
            // milik kelas sebelumnya, jadi tidak dipertahankan.
            fillMissedOptions();
            updateCurrentSchedule();
            mirrorOriginToClass(true);
            if (dateInput && timeInput) syncSchedule();
        });
    }

    studentSel.addEventListener('change', function () {
        classManual = false;
        filterClasses();
        filterOrigins();
        fillOrigin(true);
        fillMissedOptions();
        updateCurrentSchedule();
        mirrorOriginToClass(true);
        // Penyaringan & pencerminan mengubah kelas terpilih tanpa memicu event
        // change (setValue silent), jadi jadwal disegarkan manual.
        if (dateInput && timeInput) syncSchedule();
    });
    // Jalankan saat load (mode edit / old input).
    filterClasses();
    filterOrigins();
    fillOrigin(false);
    // Opsinya sudah dirender server. Disusun ulang hanya bila kelas asal baru saja
    // diisi otomatis dari enrollment murid — server belum tahu kelas itu terpilih.
    if (missedSel && !missedSel.value) fillMissedOptions();
    updateCurrentSchedule();
    mirrorOriginToClass(false);
    if (dateInput && timeInput) syncSchedule();
});
</script>
@endpush
