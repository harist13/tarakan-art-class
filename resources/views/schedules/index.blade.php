@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-start justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Jadwal & Replacement Class</h1>
        <p class="text-muted small mb-0">Proses permintaan kelas pengganti, lalu atur ketersediaan slot, hari libur, dan acara yang tampil di kalender.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
        <a href="{{ route('schedules.calendar') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3 me-1"></i>Lihat Kalender</a>
        <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i>Ajukan Replacement</a>
    </div>
</div>

{{-- ── Ringkasan ────────────────────────────────────────────────── --}}
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="{{ route('schedules.index', ['status' => 'pending']) }}" class="text-decoration-none">
            <div class="card border-left-warning h-100 py-2 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-warning text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Replacement Pending</div>
                            <div class="h4 mb-0 fw-bold text-gray-800">{{ $pendingCount }}</div>
                            <div class="small text-muted mt-1">menunggu persetujuan</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-hourglass-split fs-1" style="color:#E2E8F0;"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-success text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Slot Tersedia</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $availableSlots }} <span class="small text-muted fw-normal">/ {{ $slots->count() }}</span></div>
                        <div class="small text-muted mt-1">bisa dipilih untuk pengganti</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-check-circle fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100 py-2 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-info text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Hari Libur</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $holidays->count() }}</div>
                        <div class="small text-muted mt-1">tanggal kelas ditiadakan</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-calendar-x fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2 shadow-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <div class="text-primary text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Acara / Agenda</div>
                        <div class="h4 mb-0 fw-bold text-gray-800">{{ $calendarEvents->count() }}</div>
                        <div class="small text-muted mt-1">tampil di kalender</div>
                    </div>
                    <div class="col-auto"><i class="bi bi-calendar-event fs-1" style="color:#E2E8F0;"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Tugas utama: Daftar Request Replacement ──────────────────── --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Daftar Request Replacement Class</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:200px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari murid / kelas...">
            </div>
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">Semua Status</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="approved" @selected($status === 'approved')>Approved</option>
                <option value="rejected" @selected($status === 'rejected')>Rejected</option>
            </select>
            @if($search !== '' || $status !== '')
                <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        @include('partials.unpaid-hidden-note', ['label' => 'request replacement'])
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="text-muted small text-uppercase">
                    <tr><th>Murid</th><th>Kelas Asal</th><th>Kelas Baru</th><th>Jadwal Baru</th><th>Alasan</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td class="fw-bold">{{ $req->student->name ?? '-' }}</td>
                            <td>
                                @if($req->originClass)
                                    {{ $req->originClass->class_name }}
                                    <br><small class="text-muted">{{ $req->originClass->schedule_date->format('d M Y') }} · {{ \Illuminate\Support\Str::of($req->originClass->schedule_time)->substr(0,5) }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <i class="bi bi-arrow-right-short text-success"></i>{{ $req->classRoom->class_name ?? '-' }}
                                @if($req->classRoom)<br><small class="text-muted">{{ ucfirst($req->classRoom->class_category) }}</small>@endif
                            </td>
                            <td class="text-nowrap">
                                <i class="bi bi-calendar3 text-muted me-1"></i>{{ $req->replacement_date->format('d M Y') }}
                                <br><small class="text-muted"><i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Str::of($req->replacement_time)->substr(0,5) }}</small>
                            </td>
                            <td class="small">{{ $req->reason ?: '—' }}</td>
                            <td>
                                @php $colors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; @endphp
                                <span class="badge bg-{{ $colors[$req->request_status] }}">{{ ucfirst($req->request_status) }}</span>
                                @if($req->approver)<br><small class="text-muted">oleh {{ $req->approver->full_name }}</small>@endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($req->request_status === 'pending' && auth()->user()->isSuperAdmin())
                                    <form action="{{ route('schedules.status', $req) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="request_status" value="approved">
                                        <button class="btn btn-sm btn-success" title="Setujui"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <form action="{{ route('schedules.status', $req) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="request_status" value="rejected">
                                        <button class="btn btn-sm btn-danger" title="Tolak"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endif
                                <a href="{{ route('schedules.edit', $req) }}" class="btn btn-sm btn-info text-white" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('schedules.destroy', $req) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus request ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                            {{ ($search !== '' || $status !== '') ? 'Tidak ada request yang cocok dengan filter.' : 'Belum ada request replacement.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
    </div>
</div>

{{-- ── Pengaturan jadwal & slot ─────────────────────────────────── --}}
<h2 class="h5 fw-bold text-gray-800 mt-4 mb-1"><i class="bi bi-sliders me-2 text-secondary"></i>Pengaturan Jadwal & Slot</h2>
<p class="text-muted small mb-3">Atur slot mana yang bisa dipakai untuk kelas pengganti, serta penanda kalender (hari libur & acara).</p>

{{-- Panel kelola ketersediaan slot. --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold"><i class="bi bi-toggles me-2 text-primary"></i>Ketersediaan Slot Kelas</span>
        <small class="text-muted">Tutup slot yang tak layak agar tak muncul saat mencari kelas pengganti.</small>
    </div>
    <div class="card-body">
        {{-- Legenda status agar warna badge mudah dimengerti. --}}
        <div class="d-flex flex-wrap gap-3 small mb-3 pb-3 border-bottom">
            <span><span class="badge bg-success">Tersedia</span> bisa dipilih</span>
            <span><span class="badge bg-danger">Penuh</span> kuota habis</span>
            <span><span class="badge bg-warning">Tutor kosong</span> tutor nonaktif</span>
            <span><span class="badge bg-dark">Sudah lewat</span> jadwal berlalu</span>
            <span><span class="badge bg-info">Hari libur</span> kelas ditiadakan</span>
            <span><span class="badge bg-secondary">Ditutup</span> ditutup admin</span>
        </div>
        <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-muted small text-uppercase">
                    <tr><th>Kelas</th><th>Jadwal</th><th>Terisi</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($slots as $slot)
                        @php $av = $slot->availability(); @endphp
                        <tr>
                            <td class="fw-bold">{{ $slot->class_name }}<br><small class="text-muted">{{ $slot->class_code }} · {{ ucfirst($slot->class_category) }}</small></td>
                            <td>{{ $slot->schedule_date->format('d M Y') }}<br><small class="text-muted">{{ \Illuminate\Support\Str::of($slot->schedule_time)->substr(0,5) }}</small></td>
                            <td>{{ $slot->enrolledCount() }} / {{ $slot->capacity }}</td>
                            <td><span class="badge bg-{{ $av['color'] }}">{{ $av['text'] }}</span></td>
                            <td class="text-end">
                                @if($slot->isClosed())
                                    <form action="{{ route('classes.toggle-status', $slot) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-success" title="Buka slot"><i class="bi bi-unlock me-1"></i>Buka</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-close-slot"
                                        data-action="{{ route('classes.toggle-status', $slot) }}"
                                        data-name="{{ $slot->class_name }}"
                                        title="Tutup slot"><i class="bi bi-lock me-1"></i>Tutup</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada kelas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Penanda kalender: Hari Libur & Acara dalam satu card bertab. --}}
@php $eventTab = $errors->event->isNotEmpty(); @endphp
<div class="card mb-4">
    <div class="card-header pb-0">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $eventTab ? '' : 'active' }}" id="tab-holiday" data-bs-toggle="tab" data-bs-target="#pane-holiday" type="button" role="tab">
                    <i class="bi bi-calendar-x me-1 text-info"></i>Hari Libur
                    @if($holidays->count())<span class="badge bg-info-subtle text-info-emphasis ms-1">{{ $holidays->count() }}</span>@endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $eventTab ? 'active' : '' }}" id="tab-event" data-bs-toggle="tab" data-bs-target="#pane-event" type="button" role="tab">
                    <i class="bi bi-calendar-event me-1 text-primary"></i>Acara / Agenda
                    @if($calendarEvents->count())<span class="badge bg-primary-subtle text-primary-emphasis ms-1">{{ $calendarEvents->count() }}</span>@endif
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content">
            {{-- ── Tab: Hari Libur ── --}}
            <div class="tab-pane fade {{ $eventTab ? '' : 'show active' }}" id="pane-holiday" role="tabpanel">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Semua slot kelas pada tanggal ini otomatis tidak tersedia untuk kelas pengganti.</p>
                <form action="{{ route('holidays.store') }}" method="POST" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-sm @error('date', 'holiday') is-invalid @enderror" value="{{ old('date') }}" required>
                        @error('date', 'holiday')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small mb-1">Keterangan <span class="text-muted">(opsional)</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name') }}" maxlength="255" placeholder="mis. Libur Idul Fitri">
                    </div>
                    <div class="col-sm-2 d-grid">
                        <button class="btn btn-sm btn-info text-white"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                    </div>
                </form>
                @forelse($holidays as $holiday)
                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill me-2 mb-2 py-2 px-3">
                        <i class="bi bi-calendar-event me-1"></i>{{ $holiday->date->format('d M Y') }}
                        @if($holiday->name)<span class="text-muted">· {{ $holiday->name }}</span>@endif
                        <form action="{{ route('holidays.destroy', $holiday) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus hari libur ini?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger p-0 ms-2 align-baseline" title="Hapus"><i class="bi bi-x-circle"></i></button>
                        </form>
                    </span>
                @empty
                    <p class="text-muted small mb-0">Belum ada hari libur terdaftar.</p>
                @endforelse
            </div>

            {{-- ── Tab: Acara / Agenda ── --}}
            <div class="tab-pane fade {{ $eventTab ? 'show active' : '' }}" id="pane-event" role="tabpanel">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Rapat, pameran, atau kegiatan lain yang ingin ditampilkan di kalender.</p>
                <form action="{{ route('calendar-events.store') }}" method="POST" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-sm-3">
                        <label class="form-label small mb-1">Judul</label>
                        <input type="text" name="title" class="form-control form-control-sm @error('title', 'event') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                        @error('title', 'event')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small mb-1">Tanggal</label>
                        <input type="date" name="date" class="form-control form-control-sm @error('date', 'event') is-invalid @enderror" value="{{ old('date') }}" required>
                        @error('date', 'event')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small mb-1">Jam mulai <span class="text-muted">(opsional)</span></label>
                        <input type="time" name="start_time" class="form-control form-control-sm" value="{{ old('start_time') }}">
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label small mb-1">Jam selesai</label>
                        <input type="time" name="end_time" class="form-control form-control-sm @error('end_time', 'event') is-invalid @enderror" value="{{ old('end_time') }}">
                        @error('end_time', 'event')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-1">
                        <label class="form-label small mb-1">Warna</label>
                        <input type="color" name="color" class="form-control form-control-sm form-control-color" value="{{ old('color', '#6366F1') }}" title="Pilih warna" data-no-search>
                    </div>
                    <div class="col-sm-2 d-grid">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                    </div>
                    <div class="col-12">
                        <input type="text" name="description" class="form-control form-control-sm" value="{{ old('description') }}" maxlength="255" placeholder="Catatan (opsional)">
                    </div>
                </form>
                <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <tbody>
                            @forelse($calendarEvents as $event)
                                <tr>
                                    <td style="width:8px;"><span class="d-inline-block rounded-circle" style="width:12px;height:12px;background:{{ $event->color ?: '#6366F1' }};"></span></td>
                                    <td class="fw-semibold">{{ $event->title }}@if($event->description)<br><small class="text-muted fw-normal">{{ $event->description }}</small>@endif</td>
                                    <td class="text-nowrap">{{ $event->date->format('d M Y') }}<br><small class="text-muted">{{ $event->isAllDay() ? 'Seharian' : \Illuminate\Support\Str::of($event->start_time)->substr(0,5).($event->end_time ? '–'.\Illuminate\Support\Str::of($event->end_time)->substr(0,5) : '') }}</small></td>
                                    <td class="text-end">
                                        <form action="{{ route('calendar-events.destroy', $event) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus acara ini?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-muted small py-3">Belum ada acara terdaftar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal alasan saat menutup slot manual. --}}
<div class="modal fade" id="closeSlotModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="closeSlotForm">
            @csrf @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lock me-2 text-secondary"></i>Tutup Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Tutup slot <strong id="closeSlotName"></strong>? Slot yang ditutup tidak akan muncul saat mencari kelas pengganti.</p>
                    <label class="form-label">Alasan menutup <span class="text-muted">(opsional, tapi disarankan)</span></label>
                    <textarea name="closed_reason" class="form-control" rows="2" maxlength="255" placeholder="mis. slot dicadangkan untuk kelas gabungan"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-secondary"><i class="bi bi-lock me-1"></i>Tutup Slot</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('closeSlotModal');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('closeSlotForm');
    const nameEl = document.getElementById('closeSlotName');

    document.querySelectorAll('.btn-close-slot').forEach(function (btn) {
        btn.addEventListener('click', function () {
            form.action = btn.dataset.action;
            nameEl.textContent = btn.dataset.name;
            form.querySelector('[name="closed_reason"]').value = '';
            modal.show();
        });
    });
});
</script>
@endpush
