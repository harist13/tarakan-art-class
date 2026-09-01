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
    {{-- Tiap scorecard membuka panel yang mengelolanya — angka yang menarik
         perhatian dan tempat menindaklanjutinya jadi satu klik. --}}
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="{{ route('schedules.index', ['tab' => 'slots']) }}" class="text-decoration-none">
            <div class="card border-left-success h-100 py-2 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-success text-uppercase mb-1" style="font-size:0.7rem; font-weight:800; letter-spacing:.5px;">Slot Tersedia</div>
                            <div class="h4 mb-0 fw-bold text-gray-800">{{ $availableSlots }} <span class="small text-muted fw-normal">/ {{ $totalSlots }}</span></div>
                            <div class="small text-muted mt-1">bisa dipilih untuk pengganti</div>
                        </div>
                        <div class="col-auto"><i class="bi bi-check-circle fs-1" style="color:#E2E8F0;"></i></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="{{ route('schedules.index', ['tab' => 'markers']) }}" class="text-decoration-none">
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
        </a>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <a href="{{ route('schedules.index', ['tab' => 'markers']) }}" class="text-decoration-none">
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
        </a>
    </div>
</div>

@php
    // Error validasi hari libur / acara harus terlihat, jadi panelnya dipaksa
    // terbuka — kalau tidak, pesan errornya tersembunyi di panel yang tertutup.
    $eventTab = $errors->event->isNotEmpty();
    $tab = ($errors->holiday->isNotEmpty() || $eventTab) ? 'markers' : $tab;
@endphp

{{-- Switch panel: satu halaman, tiga pekerjaan yang berdiri sendiri. Ditumpuk
     vertikal seperti sebelumnya, panel slot & penanda kalender praktis tak
     pernah terlihat tanpa menggulir jauh. --}}
<div class="btn-group mb-4 shadow-sm flex-wrap" role="group" aria-label="Pilih panel">
    <input type="radio" class="btn-check" name="panelToggle" id="toggleRequests" autocomplete="off" @checked($tab === 'requests')>
    <label class="btn btn-outline-primary" for="toggleRequests">
        <i class="bi bi-arrow-left-right me-1"></i>Request Replacement
        @if($pendingCount)<span class="badge rounded-pill ms-1 fw-bold" style="background-color: rgba(245, 136, 12, 1); color: #FFFFFF !important;">{{ $pendingCount }}</span>@endif
    </label>
    <input type="radio" class="btn-check" name="panelToggle" id="toggleSlots" autocomplete="off" @checked($tab === 'slots')>
    <label class="btn btn-outline-primary" for="toggleSlots">
        <i class="bi bi-toggles me-1"></i>Ketersediaan Slot
        <span class="badge rounded-pill ms-1 fw-bold" style="background-color: #15803D; color: #FFFFFF !important;">{{ $availableSlots }}</span>
    </label>
    <input type="radio" class="btn-check" name="panelToggle" id="toggleMarkers" autocomplete="off" @checked($tab === 'markers')>
    <label class="btn btn-outline-primary" for="toggleMarkers">
        <i class="bi bi-calendar-week me-1"></i>Hari Libur &amp; Acara
        <span class="badge rounded-pill ms-1 fw-bold" style="background-color: #0891B2; color: #FFFFFF !important;">{{ $holidays->count() + $calendarEvents->count() }}</span>
    </label>
</div>

{{-- ── Panel 1: Daftar Request Replacement ──────────────────────── --}}
<div id="panelRequests" @if($tab !== 'requests') style="display:none;" @endif>
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
        @include('partials.arrears-note', [
            'count' => $arrearsCount,
            'label' => 'request replacement pending',
            'effect' => 'Pengajuan baru untuk mereka ditolak sampai tunggakan lunas — tinjau dulu sebelum approve.',
        ])
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="text-muted small text-uppercase">
                    {{-- Istilahnya disamakan dengan form pengajuan: "kelas asal"
                         (sesi yang dilewatkan) → "kelas pengganti". --}}
                    <tr><th>Murid</th><th>Kelas Asal</th><th>Kelas Pengganti</th><th>Sesi Pengganti</th><th>Alasan</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <td class="fw-bold">{{ $req->student->name ?? '-' }}</td>
                            <td>
                                @if($req->originClass)
                                    {{ $req->originClass->class_name }}
                                    <br><small class="text-muted">{{ $req->originClass->scheduleLabel() }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <i class="bi bi-arrow-right-short text-success"></i>{{ $req->classRoom->class_name ?? '-' }}
                                @if($req->classRoom)<br><small class="text-muted">{{ ucfirst($req->classRoom->class_category) }}</small>@endif
                            </td>
                            {{-- Jam ditampilkan 12 jam seperti di form pengajuan,
                                 supaya tidak ada dua konvensi waktu dalam satu alur. --}}
                            <td class="text-nowrap">
                                <i class="bi bi-calendar3 text-muted me-1"></i>{{ $req->replacement_date->format('d M Y') }}
                                <br><small class="text-muted"><i class="bi bi-clock me-1"></i>{{ \Illuminate\Support\Carbon::parse($req->replacement_time)->format('g.i A') }}</small>
                            </td>
                            <td class="small">{{ $req->reason ?: '—' }}</td>
                            <td>
                                @php
                                    $reqStyles = [
                                        'pending'  => ['bg' => 'rgba(245, 136, 12, 1)', 'label' => 'Pending'],
                                        'approved' => ['bg' => '#15803D',               'label' => 'Approved'],
                                        'rejected' => ['bg' => '#DC2626',               'label' => 'Rejected'],
                                    ];
                                    $st = $reqStyles[$req->request_status] ?? ['bg' => '#475569', 'label' => ucfirst($req->request_status)];
                                @endphp
                                <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: {{ $st['bg'] }};">{{ $st['label'] }}</span>
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

</div>{{-- /panelRequests --}}

{{-- ── Panel 2: Ketersediaan Slot Kelas ─────────────────────────── --}}
<div id="panelSlots" @if($tab !== 'slots') style="display:none;" @endif>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap"><i class="bi bi-toggles me-2 text-primary"></i>Ketersediaan Slot Kelas</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            {{-- Menjaga panel ini tetap terbuka setelah filter disubmit. --}}
            <input type="hidden" name="tab" value="slots">
            <div class="input-group input-group-sm" style="width:200px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="slot_search" value="{{ $slotSearch }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari kelas / kode...">
            </div>
            <select name="slot_status" class="form-select form-select-sm" style="width:160px;">
                <option value="">Semua Status</option>
                <option value="tersedia" @selected($slotStatus === 'tersedia')>Tersedia</option>
                <option value="penuh" @selected($slotStatus === 'penuh')>Penuh</option>
                <option value="tanpa-tutor" @selected($slotStatus === 'tanpa-tutor')>Tutor kosong</option>
                <option value="lewat" @selected($slotStatus === 'lewat')>Sudah lewat</option>
                <option value="ditutup" @selected($slotStatus === 'ditutup')>Ditutup</option>
            </select>
            @if($slotSearch !== '' || $slotStatus !== '')
                <a href="{{ route('schedules.index', ['tab' => 'slots']) }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Tutup slot yang tak layak agar tak muncul saat mencari kelas pengganti.</p>
        {{-- Legenda status agar warna badge mudah dimengerti. Isinya persis
             keluaran availability(): "Hari libur" sengaja tak ada di sini karena
             bukan status slot — tanggal libur hanya dilewati saat menghitung sesi
             berikutnya, slotnya sendiri tetap tersedia. --}}
        <div class="d-flex flex-wrap gap-3 small mb-3 pb-3 border-bottom">
            <span><span class="badge bg-success">Tersedia</span> bisa dipilih</span>
            <span><span class="badge bg-danger">Penuh</span> kuota habis</span>
            <span><span class="badge bg-warning">Tutor kosong</span> tutor nonaktif</span>
            <span><span class="badge bg-dark">Sudah lewat</span> tak punya sesi mendatang</span>
            <span><span class="badge bg-secondary">Ditutup</span> ditutup admin</span>
        </div>
        {{-- Tanpa max-height: di panelnya sendiri tabel ini tak perlu diperas
             jadi kotak bergulir di dalam halaman yang juga bergulir. --}}
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="text-muted small text-uppercase">
                    <tr><th>Kelas</th><th>Jadwal</th><th>Terisi</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($slots as $slot)
                        @php $av = $slot->availability(); @endphp
                        <tr>
                            <td class="fw-bold">{{ $slot->class_name }}<br><small class="text-muted">{{ $slot->class_code }} · {{ ucfirst($slot->class_category) }}</small></td>
                            @php $nextSession = $slot->nextOccurrence(); @endphp
                            <td>
                                {{ $slot->scheduleLabel() }}
                                @if($slot->is_recurring)
                                    <br><small class="text-muted">{{ $nextSession ? 'Sesi berikutnya '.$nextSession->format('d M Y') : 'Belum ada sesi mendatang' }}</small>
                                @endif
                            </td>
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
                        <tr><td colspan="5" class="text-center text-muted py-4">
                            {{ ($slotSearch !== '' || $slotStatus !== '') ? 'Tidak ada slot yang cocok dengan filter.' : 'Belum ada kelas.' }}
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>{{-- /panelSlots --}}

{{-- ── Panel 3: Penanda kalender (Hari Libur & Acara) ───────────── --}}
<div id="panelMarkers" @if($tab !== 'markers') style="display:none;" @endif>
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

</div>{{-- /panelMarkers --}}

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
    // ── Switch panel: Request Replacement / Ketersediaan Slot / Hari Libur & Acara ──
    (function () {
        const PANELS = [
            { tab: 'requests', panel: 'panelRequests', toggle: 'toggleRequests' },
            { tab: 'slots', panel: 'panelSlots', toggle: 'toggleSlots' },
            { tab: 'markers', panel: 'panelMarkers', toggle: 'toggleMarkers' },
        ];

        function applyPanel(tab) {
            PANELS.forEach(function (p) {
                const el = document.getElementById(p.panel);
                if (el) el.style.display = p.tab === tab ? '' : 'none';
            });

            // Simpan tab di URL agar bertahan saat reload, setelah submit filter,
            // dan setelah form hari libur / acara redirect back().
            const url = new URL(location);
            if (tab === 'requests') { url.searchParams.delete('tab'); } else { url.searchParams.set('tab', tab); }
            history.replaceState(null, '', url);
        }

        let aktif = 'requests';
        PANELS.forEach(function (p) {
            const toggle = document.getElementById(p.toggle);
            if (!toggle) return;
            if (toggle.checked) aktif = p.tab;
            toggle.addEventListener('change', function () { applyPanel(p.tab); });
        });
        applyPanel(aktif);
    })();

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
