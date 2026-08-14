<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" id="student_id" class="form-select" required>
            <option value="">— Pilih Murid —</option>
            @foreach($students as $student)
                @php $enrolled = $student->relationLoaded('classes') ? $student->classes->pluck('id') : collect(); @endphp
                <option value="{{ $student->id }}" data-category="{{ $student->class_type }}" data-origin="{{ $enrolled->first() }}" data-enrolled="{{ $enrolled->join(',') }}" @selected(old('student_id', $request->student_id ?? request('student_id', '')) == $student->id)>{{ $student->name }} ({{ $student->student_id }})</option>
            @endforeach
        </select>
        @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted d-block mt-1">
            <i class="bi bi-cash-coin me-1"></i>Murid dengan invoice <strong>lewat jatuh tempo</strong> tidak bisa dipilih —
            kelas pengganti adalah fasilitas tambahan. Invoice yang belum jatuh tempo tidak menghalangi.
            @if($students->isEmpty())
                <span class="text-danger d-block">Semua murid sedang menunggak — lunasi tunggakannya di <a href="{{ route('payments.index') }}">menu Pembayaran</a>.</span>
            @endif
        </small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Kelas Asal <span class="text-muted">(sebelumnya)</span></label>
        <select name="origin_class_id" id="origin_class_id" class="form-select @error('origin_class_id') is-invalid @enderror">
            <option value="">— Pilih Kelas Asal —</option>
            @foreach($classes as $class)
                {{-- data-day/data-time dipakai untuk mendeteksi sesi pengganti yang
                     masih sama persis dengan jadwal kelas asal. --}}
                <option value="{{ $class->id }}" data-category="{{ $class->class_category }}"
                    data-day="{{ $class->day_of_week }}" data-time="{{ $class->timeLabel() }}"
                    @selected(old('origin_class_id', $request->origin_class_id ?? request('origin_class_id', '')) == $class->id)>
                    {{ $class->class_name }} ({{ ucfirst($class->class_category) }}) — {{ $class->scheduleLabel() }}
                </option>
            @endforeach
        </select>
        @error('origin_class_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        {{-- Peringatan saat murid belum punya enrollment aktif — kelas asal harus dipilih manual. --}}
        <div class="small text-danger mt-1 d-none" id="noClassWarning"><i class="bi bi-exclamation-triangle me-1"></i>Murid ini belum memilih kelas.</div>
        <small class="text-muted d-block mt-1" id="originHint"><i class="bi bi-box-arrow-left me-1"></i>Slot yang ditinggalkan murid. Terisi otomatis dari kelas aktifnya.</small>
        <small class="text-muted d-block mt-1"><i class="bi bi-arrow-repeat me-1"></i>Boleh sama dengan kelas baru, asalkan hari &amp; jam penggantinya berbeda dari jadwal kelas asal — kalau sama persis, tidak ada sesi yang berpindah.</small>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Kelas Baru <span class="text-muted">(yang sekarang)</span></label>
        @php $availableCount = $classes->filter->isAvailable()->count(); @endphp
        <select name="class_id" id="class_id" class="form-select" required>
            <option value="">— Pilih Kelas —</option>
            @foreach($classes as $class)
                @php $selected = old('class_id', $request->class_id ?? request('class_id', '')) == $class->id; @endphp
                {{-- Hanya tampilkan slot yang tersedia; kelas yang sedang dipilih (saat edit) tetap muncul. --}}
                @if($class->isAvailable() || $selected)
                    {{-- data-date/data-time: sumber isian otomatis tanggal & jam pengganti.
                         Tanggalnya sesi mingguan berikutnya, bukan tanggal mulai berlaku slot. --}}
                    @php $nextSession = $class->nextOccurrence(); @endphp
                    <option value="{{ $class->id }}" data-category="{{ $class->class_category }}"
                        data-date="{{ $nextSession?->format('Y-m-d') }}"
                        data-day="{{ $class->day_of_week }}"
                        data-time="{{ $class->timeLabel() }}" @selected($selected)>
                        {{ $class->class_name }} ({{ ucfirst($class->class_category) }}) — {{ $class->scheduleLabel() }} · {{ $class->availability()['text'] }}
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
    {{-- Tanggal & jam pengganti terisi otomatis dari jadwal kelas tujuan, tapi tetap
         bisa ditimpa admin bila sesinya digeser. Dikosongkan = ikut jadwal kelas
         (diisi ulang di server), jadi keduanya sengaja tidak `required`. --}}
    <div class="col-md-6 mb-3">
        <label class="form-label">Tanggal Pengganti</label>
        <input type="date" name="replacement_date" id="replacement_date" class="form-control @error('replacement_date') is-invalid @enderror" value="{{ old('replacement_date', isset($request) ? $request->replacement_date->format('Y-m-d') : request('replacement_date', '')) }}">
        @error('replacement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jam Pengganti</label>
        <input type="time" name="replacement_time" id="replacement_time" class="form-control @error('replacement_time') is-invalid @enderror" value="{{ old('replacement_time', isset($request) ? \Illuminate\Support\Str::of($request->replacement_time)->substr(0,5) : request('replacement_time', '')) }}">
        @error('replacement_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 mb-3 d-flex flex-wrap align-items-center gap-2">
        <span class="small text-muted" id="scheduleSyncHint">
            <i class="bi bi-calendar-event me-1"></i>Pilih kelas tujuan — tanggal &amp; jam akan terisi otomatis dari jadwalnya.
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="scheduleSyncBtn">
            <i class="bi bi-arrow-repeat me-1"></i>Samakan dengan jadwal kelas
        </button>
    </div>
    <div class="col-12 mb-3">
        <label class="form-label">Alasan</label>
        <textarea name="reason" class="form-control" rows="2">{{ old('reason', $request->reason ?? '') }}</textarea>
    </div>
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
    const syncBtn = document.getElementById('scheduleSyncBtn');
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
                    date: o.dataset.date || prev.date || '',
                    time: o.dataset.time || prev.time || '',
                    day: o.dataset.day || prev.day || '',
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
            : (conflict ? 'Sesi pengganti masih sama dengan jadwal kelas asal' : '');
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

    // ── Tanggal & jam pengganti mengikuti jadwal kelas tujuan ──

    // Sekali admin mengetik sendiri sebuah field, field itu berhenti ikut jadwal
    // kelas sampai tombol "Samakan" ditekan.
    let dateManual = false;
    let timeManual = false;

    function classSchedule() {
        return classSchedules[classSel.value] || null;
    }

    /** "Rab, 20 Agu 2026 09:00" — untuk ditampilkan di hint. */
    function scheduleLabel(s) {
        const p = s.date.split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);
        const tanggal = isNaN(d.getTime())
            ? s.date
            : d.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });

        return (tanggal + ' ' + s.time).trim();
    }

    const DAY_NAMES = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** "Senin, 10:00" — jadwal rutin mingguan, bukan satu tanggal. */
    function weeklyLabel(s) {
        return ((DAY_NAMES[s.day] || '') + ', ' + s.time).trim();
    }

    /**
     * Sesi pengganti masih sama persis dengan jadwal kelas asal: kelas yang sama,
     * hari mingguan yang sama, jam yang sama. Dibandingkan hari (getDay, 0 = Minggu
     * seperti Carbon::dayOfWeek) karena slot berulang tiap pekan — "Senin depan"
     * tetap sesi yang sama dengan "Senin" kelas asal.
     */
    function sameAsOrigin() {
        if (!originSel || !dateInput || !timeInput) return false;
        if (!originSel.value || originSel.value !== classSel.value) return false;
        if (!dateInput.value || !timeInput.value) return false;

        const s = classSchedules[originSel.value];
        if (!s || s.day === '' || !s.time) return false;

        const p = dateInput.value.split('-');
        const d = new Date(+p[0], +p[1] - 1, +p[2]);
        if (isNaN(d.getTime())) return false;

        return String(d.getDay()) === String(s.day) && timeInput.value === s.time;
    }

    function updateSyncHint() {
        if (!syncHint) return;
        const s = classSchedule();

        // Konflik dengan kelas asal diutamakan: ini satu-satunya kondisi yang
        // benar-benar menghalangi submit.
        if (sameAsOrigin()) {
            syncHint.className = 'small text-danger';
            syncHint.innerHTML = '<i class="bi bi-x-octagon me-1"></i>Sesi pengganti sama persis dengan jadwal kelas asal (<strong>'
                + weeklyLabel(classSchedules[originSel.value]) + '</strong>) — tidak ada sesi yang berpindah. Ubah hari atau jamnya, atau pilih kelas tujuan lain.';
            if (syncBtn) syncBtn.classList.add('d-none');
            updateSubmitState();

            return;
        }

        if (!s) {
            syncHint.className = 'small text-muted';
            syncHint.innerHTML = '<i class="bi bi-calendar-event me-1"></i>Pilih kelas tujuan — tanggal &amp; jam akan terisi otomatis dari jadwalnya.';
            if (syncBtn) syncBtn.classList.add('d-none');
            updateSubmitState();

            return;
        }

        const label = '<strong>' + scheduleLabel(s) + '</strong>';
        const kosong = !dateInput.value || !timeInput.value;
        const beda = (!!dateInput.value && !!s.date && dateInput.value !== s.date)
            || (!!timeInput.value && !!s.time && timeInput.value !== s.time);

        if (kosong) {
            syncHint.className = 'small text-muted';
            syncHint.innerHTML = '<i class="bi bi-calendar-event me-1"></i>Dikosongkan — akan mengikuti jadwal kelas tujuan (' + label + ').';
        } else if (beda) {
            syncHint.className = 'small text-warning-emphasis';
            syncHint.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Diisi manual, berbeda dari jadwal kelas tujuan (' + label + ').';
        } else {
            syncHint.className = 'small text-muted';
            syncHint.innerHTML = '<i class="bi bi-calendar-check me-1"></i>Mengikuti jadwal kelas tujuan (' + label + '). Boleh diubah bila sesinya digeser.';
        }

        if (syncBtn) syncBtn.classList.toggle('d-none', !(kosong || beda));
        updateSubmitState();
    }

    // Isi field yang masih "otomatis" dari jadwal kelas tujuan.
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
        // Mengganti kelas asal bisa memunculkan/menghilangkan konflik tanpa
        // mengubah tanggal & jam sama sekali.
        if (originSel) originSel.addEventListener('change', updateSyncHint);

        if (syncBtn) {
            syncBtn.addEventListener('click', function () {
                dateManual = false;
                timeManual = false;
                syncSchedule();
            });
        }
    }

    studentSel.addEventListener('change', function () {
        filterClasses();
        filterOrigins();
        fillOrigin(true);
        // Penyaringan bisa melepas kelas yang tadinya terpilih tanpa memicu
        // event change (setValue silent), jadi jadwal disegarkan manual.
        if (dateInput && timeInput) syncSchedule();
    });
    // Jalankan saat load (mode edit / old input).
    filterClasses();
    filterOrigins();
    fillOrigin(false);
    if (dateInput && timeInput) syncSchedule();
});
</script>
@endpush
