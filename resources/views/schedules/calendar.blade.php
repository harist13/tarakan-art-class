@extends('layouts.app')

@section('content')
<style>
    /* Event tampil sebagai label penuh berwarna (background = status) */
    #calendar .fc-event {
        border: none;
        border-radius: 6px;
        padding: 2px 6px;
        margin: 1px 2px;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 1px 2px rgba(0,0,0,0.08);
    }
    #calendar .fc-event .fc-event-main,
    #calendar .fc-event .fc-event-title,
    #calendar .fc-event .fc-event-time {
        color: #fff !important;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #calendar .fc-event:hover { filter: brightness(0.94); }
    /* Tampilan daftar (listMonth) tetap pakai teks gelap agar terbaca */
    #calendar .fc-list-event .fc-list-event-title a { color: #334155 !important; }
</style>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Kalender Jadwal</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> Tampilan Daftar</a>
        <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Ajukan Replacement</a>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span>Jadwal Kelas & Replacement Class</span>
        <div class="d-flex flex-wrap align-items-center gap-3 small">
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#0EA5E9;"></span>Kelas (Available)</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#94A3B8;"></span>Kelas Penuh/Ditutup</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#F59E0B;"></span>Repl. Pending</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#10B981;"></span>Repl. Approved</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#EF4444;"></span>Repl. Rejected</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#EA580C;"></span>Hari Libur</span>
            <span><span class="d-inline-block rounded-circle me-1" style="width:12px;height:12px;background:#6366F1;"></span>Acara</span>
            <div class="form-check form-switch ms-2">
                <input class="form-check-input" type="checkbox" id="onlyAvailable" checked>
                <label class="form-check-label" for="onlyAvailable">Hanya slot available</label>
            </div>
            <div class="d-flex align-items-center gap-2 ms-2">
                <label for="replacementStudent" class="text-nowrap mb-0 fw-semibold"><i class="bi bi-search me-1"></i>Cari kelas pengganti</label>
                <select id="replacementStudent" class="form-select form-select-sm" style="width:220px;">
                    <option value="">— Pilih murid —</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" data-name="{{ $student->name }}" data-category="{{ $student->class_type }}">{{ $student->name }} ({{ ucfirst($student->class_type) }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="card-body">
        {{-- Banner mode "cari kelas pengganti": tampil saat seorang murid dipilih. --}}
        <div id="replacementBanner" class="alert alert-primary d-none align-items-center justify-content-between flex-wrap gap-2" role="alert">
            <span><i class="bi bi-funnel-fill me-1"></i>Menampilkan <strong id="rbCount">0</strong> slot tersedia untuk <strong id="rbName"></strong> (tipe <strong id="rbCategory"></strong>). Slot beda tipe juga bisa dipilih. Klik slot untuk mengajukan replacement.</span>
            <button type="button" id="rbClear" class="btn btn-sm btn-outline-primary"><i class="bi bi-x-lg me-1"></i>Keluar mode</button>
        </div>
        <div id="calendar"></div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">Detail Jadwal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="eventModalBody"></div>
            <div class="modal-footer">
                <a href="#" id="eventModalLink" class="btn btn-primary d-none"><i class="bi bi-pencil me-1"></i> Kelola Replacement</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const events = @json($events);
    const createUrl = @json(route('schedules.create'));
    const modalEl = document.getElementById('eventModal');
    const modal = new bootstrap.Modal(modalEl);

    const onlyAvailable = document.getElementById('onlyAvailable');
    const studentSel = document.getElementById('replacementStudent');
    const banner = document.getElementById('replacementBanner');

    // Murid yang sedang dipilih untuk "cari kelas pengganti" (atau null).
    function currentStudent() {
        const opt = studentSel.selectedOptions[0];
        if (!opt || !opt.value) return null;
        return { id: opt.value, name: opt.dataset.name, cat: opt.dataset.category };
    }

    // Slot yang bisa dipakai murid: kelas reguler & masih available.
    // Tipe kelas boleh berbeda — murid diizinkan replacement lintas tipe.
    function matchesStudent(ev, stu) {
        const p = ev.extendedProps || {};
        return p.type === 'Kelas Reguler' && p.available === true;
    }

    /** Slot setipe dengan murid — dipakai sebagai penanda, bukan penyaring. */
    function sameType(ev, stu) {
        return (ev.extendedProps || {}).cat === stu.cat;
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: 'dayGridMonth',
        firstDay: 1,
        height: 'auto',
        eventDisplay: 'block',
        displayEventTime: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        buttonText: { today: 'Hari Ini', month: 'Bulan', week: 'Minggu', list: 'Daftar' },
        slotMinTime: '07:00:00',
        slotMaxTime: '21:00:00',
        nowIndicator: true,
        events: events,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const p = info.event.extendedProps;
            document.getElementById('eventModalTitle').textContent = info.event.title;

            let rows = `<p class="mb-1"><strong>Jenis:</strong> ${p.type}</p>`;
            const waktu = info.event.allDay
                ? info.event.start.toLocaleDateString('id-ID', { dateStyle: 'full' })
                : info.event.start.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' });
            rows += `<p class="mb-1"><strong>Waktu:</strong> ${waktu}</p>`;
            if (p.code)        rows += `<p class="mb-1"><strong>Kode Kelas:</strong> ${p.code}</p>`;
            if (p.category)    rows += `<p class="mb-1"><strong>Kategori:</strong> ${p.category}</p>`;
            if (p.tutor)       rows += `<p class="mb-1"><strong>Tutor:</strong> ${p.tutor}</p>`;
            if (p.occupancy)   rows += `<p class="mb-1"><strong>Terisi:</strong> ${p.occupancy}</p>`;
            if (p.availability) rows += `<p class="mb-1"><strong>Ketersediaan:</strong> ${p.availability}</p>`;
            if (p.originClass) rows += `<p class="mb-1"><strong>Kelas Asal (sebelumnya):</strong> ${p.originClass}</p>`;
            if (p.newClass)    rows += `<p class="mb-1"><strong>Kelas Baru (sekarang):</strong> ${p.newClass}</p>`;
            if (p.status)   rows += `<p class="mb-1"><strong>Status:</strong> ${p.status}</p>`;
            if (p.reason)   rows += `<p class="mb-1"><strong>Alasan:</strong> ${p.reason}</p>`;
            if (p.note && p.note !== '-') rows += `<p class="mb-1"><strong>Catatan:</strong> ${p.note}</p>`;
            document.getElementById('eventModalBody').innerHTML = rows;

            const link = document.getElementById('eventModalLink');
            const stu = currentStudent();
            if (stu && matchesStudent(info.event, stu)) {
                // Beda tipe tidak menghalangi, tapi perlu disadari admin sebelum mengajukan.
                if (!sameType(info.event, stu)) {
                    document.getElementById('eventModalBody').insertAdjacentHTML('beforeend',
                        '<div class="alert alert-warning small mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Slot ini bertipe <strong>' + p.category + '</strong>, sedangkan murid bertipe <strong>' + stu.cat + '</strong>. Tetap bisa diajukan.</div>');
                }
                // Mode cari pengganti: langsung ajukan replacement untuk murid & slot ini.
                const start = info.event.start;
                const params = new URLSearchParams({
                    student_id: stu.id,
                    class_id: p.classId,
                    replacement_date: start.toLocaleDateString('en-CA'), // YYYY-MM-DD lokal
                    replacement_time: start.toTimeString().slice(0, 5),
                });
                link.href = createUrl + '?' + params.toString();
                link.innerHTML = '<i class="bi bi-send me-1"></i> Ajukan Replacement untuk ' + stu.name;
                link.classList.remove('d-none');
            } else if (info.event.url) {
                link.href = info.event.url;
                link.innerHTML = '<i class="bi bi-pencil me-1"></i> Kelola Replacement';
                link.classList.remove('d-none');
            } else {
                link.classList.add('d-none');
            }
            modal.show();
        }
    });
    calendar.render();

    // Susun daftar event yang tampil sesuai mode aktif.
    // Prioritas: mode "cari kelas pengganti" (per murid) > toggle "hanya slot available".
    function visibleEvents() {
        const stu = currentStudent();
        if (stu) {
            // Slot cocok murid + hari libur & acara (tetap ditampilkan sebagai konteks).
            return events.filter(function (ev) {
                const p = ev.extendedProps || {};
                return matchesStudent(ev, stu) || p.holiday === true || p.type === 'Acara';
            });
        }
        if (!onlyAvailable.checked) return events;
        return events.filter(function (ev) {
            const p = ev.extendedProps || {};
            return !(p.type === 'Kelas Reguler' && p.available !== true);
        });
    }

    function updateBanner() {
        const stu = currentStudent();
        if (!stu) { banner.classList.add('d-none'); banner.classList.remove('d-flex'); return; }
        document.getElementById('rbName').textContent = stu.name;
        document.getElementById('rbCategory').textContent = stu.cat;
        document.getElementById('rbCount').textContent = events.filter(function (ev) { return matchesStudent(ev, stu); }).length;
        banner.classList.remove('d-none');
        banner.classList.add('d-flex');
        // Toggle "hanya available" tidak relevan saat mode ini aktif.
        onlyAvailable.disabled = true;
    }

    function applyFilter() {
        // Susun ulang event source agar perubahan pasti ter-render (berlaku di semua view & navigasi bulan).
        calendar.getEventSources().forEach(function (source) { source.remove(); });
        calendar.addEventSource(visibleEvents());
        updateBanner();
    }

    onlyAvailable.addEventListener('change', applyFilter);
    studentSel.addEventListener('change', applyFilter);
    document.getElementById('rbClear').addEventListener('click', function () {
        studentSel.value = '';
        onlyAvailable.disabled = false;
        applyFilter();
    });

    // Terapkan filter saat load agar default "Hanya slot available" (checked) langsung berlaku.
    applyFilter();
});
</script>
@endpush
