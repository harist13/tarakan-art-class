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
            <div class="form-check form-switch ms-2">
                <input class="form-check-input" type="checkbox" id="onlyAvailable">
                <label class="form-check-label" for="onlyAvailable">Hanya slot available</label>
            </div>
        </div>
    </div>
    <div class="card-body">
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
    const modalEl = document.getElementById('eventModal');
    const modal = new bootstrap.Modal(modalEl);

    const onlyAvailable = document.getElementById('onlyAvailable');

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
            rows += `<p class="mb-1"><strong>Waktu:</strong> ${info.event.start.toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' })}</p>`;
            if (p.code)        rows += `<p class="mb-1"><strong>Kode Kelas:</strong> ${p.code}</p>`;
            if (p.category)    rows += `<p class="mb-1"><strong>Kategori:</strong> ${p.category}</p>`;
            if (p.tutor)       rows += `<p class="mb-1"><strong>Tutor:</strong> ${p.tutor}</p>`;
            if (p.occupancy)   rows += `<p class="mb-1"><strong>Terisi:</strong> ${p.occupancy}</p>`;
            if (p.availability) rows += `<p class="mb-1"><strong>Ketersediaan:</strong> ${p.availability}</p>`;
            if (p.class)       rows += `<p class="mb-1"><strong>Kelas Asal:</strong> ${p.class}</p>`;
            if (p.status)   rows += `<p class="mb-1"><strong>Status:</strong> ${p.status}</p>`;
            if (p.reason)   rows += `<p class="mb-1"><strong>Alasan:</strong> ${p.reason}</p>`;
            document.getElementById('eventModalBody').innerHTML = rows;

            const link = document.getElementById('eventModalLink');
            if (info.event.url) {
                link.href = info.event.url;
                link.classList.remove('d-none');
            } else {
                link.classList.add('d-none');
            }
            modal.show();
        }
    });
    calendar.render();

    // Filter "hanya slot available": sembunyikan kelas reguler yang penuh/ditutup.
    // Replacement class & kelas yang masih available tetap tampil.
    function visibleEvents() {
        if (!onlyAvailable.checked) return events;
        return events.filter(function (ev) {
            const p = ev.extendedProps || {};
            return !(p.type === 'Kelas Reguler' && p.available !== true);
        });
    }

    function applyFilter() {
        // Susun ulang event source agar perubahan pasti ter-render (berlaku di semua view & navigasi bulan).
        calendar.getEventSources().forEach(function (source) { source.remove(); });
        calendar.addEventSource(visibleEvents());
    }
    onlyAvailable.addEventListener('change', applyFilter);
});
</script>
@endpush
