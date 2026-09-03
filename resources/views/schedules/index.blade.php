@extends('layouts.app')

@push('styles')
<style>
    /* ── Kartu ringkasan ──
       Warnanya satu variabel per kartu (--stat-color), bukan tiga kelas utilitas:
       nada kartu di sini berubah menurut isinya (nol pengajuan tidak berwarna
       sama dengan sepuluh), dan itu keputusan di Blade, bukan di stylesheet.

       Warnanya sendiri bukan --badge-*-bg: token itu warna latar badge yang
       dipasangkan dengan teks putih, dan amber-nya cuma 2,6:1 di atas kartu putih
       — persis kegagalan kontras .text-warning yang dulu dipakai di sini. Yang di
       bawah dipilih agar lolos 4,5:1 sebagai teks; sebagai garis, chip, dan meter
       ia otomatis lolos juga. */
    :root {
        --stat-warning: #B45309;
        --stat-success: #15803D;
        --stat-danger: #B91C1C;
        --stat-neutral: #475569;
    }
    [data-bs-theme="dark"] {
        --stat-warning: #FCD34D;
        --stat-success: #4ADE80;
        --stat-danger: #FCA5A5;
        --stat-neutral: #CBD5E1;
    }

    .stat-card {
        --stat-tint: color-mix(in srgb, var(--stat-color) 12%, transparent);
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
        height: 100%;
        padding: 1.15rem 1.35rem;
        border: 1px solid var(--border);
        border-left: 0.35rem solid var(--stat-color);
        border-radius: 1rem;
        background: var(--surface);
        box-shadow: 0 1px 2px var(--shadow-sm);
        color: var(--text);
        text-decoration: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    /* Seluruh kartu memang tautan, jadi ia harus terlihat begitu — sebelumnya
       tak ada satu pun isyarat bahwa kartu ini bisa diklik. */
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px var(--shadow-md);
        border-color: var(--stat-color);
        color: var(--text);
    }
    .stat-card:focus-visible {
        outline: 2px solid var(--stat-color);
        outline-offset: 2px;
    }

    .stat-head { display: flex; align-items: center; gap: 0.55rem; }
    .stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.9rem;
        height: 1.9rem;
        border-radius: 0.55rem;
        background: var(--surface-2);
        background: var(--stat-tint);
        color: var(--stat-color);
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .stat-label {
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        /* Token badge yang memang sudah diuji kontrasnya, bukan .text-warning
           yang tak terbaca di atas putih. */
        color: var(--stat-color);
    }

    .stat-value {
        display: flex;
        align-items: baseline;
        gap: 0.35rem;
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
        /* Angka selebar sama: nilai yang berubah tidak menggeser tata letak. */
        font-variant-numeric: tabular-nums;
    }
    .stat-total { font-size: 1rem; font-weight: 600; color: var(--text-muted); }

    /* Perbandingan tersedia/total sulit dinilai dari dua angka saja; meter
       menjawabnya tanpa perlu dibaca. */
    .stat-meter {
        height: 5px;
        border-radius: 999px;
        background: var(--border);
        background: color-mix(in srgb, var(--stat-color) 18%, transparent);
        overflow: hidden;
    }
    .stat-meter-fill {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: var(--stat-color);
    }

    .stat-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: auto;
    }
    .stat-note { font-size: 0.8rem; color: var(--text-muted); }
    .stat-action {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--stat-color);
        white-space: nowrap;
    }
    .stat-action i { transition: transform 0.15s ease; }
    .stat-card:hover .stat-action i { transform: translateX(3px); }

    @media (prefers-reduced-motion: reduce) {
        .stat-card,
        .stat-action i { transition: none; }
        .stat-card:hover { transform: none; }
        .stat-card:hover .stat-action i { transform: none; }
    }

    /* ── Daftar slot per kategori ── */
    .slot-group + .slot-group { margin-top: 0.6rem; }
    .slot-group-head {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        width: 100%;
        padding: 0.7rem 0.9rem;
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        background: var(--surface);
        color: var(--text);
        text-align: left;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .slot-group-head:hover { border-color: var(--primary-color); }
    .slot-group-head[aria-expanded="true"] { border-color: var(--primary-color); }
    .slot-group-name { font-weight: 700; flex-grow: 1; }
    .slot-chevron { transition: transform 0.15s ease; color: var(--text-muted); }
    .slot-group-head[aria-expanded="true"] .slot-chevron { transform: rotate(90deg); }
    .slot-group-body { padding: 0.6rem 0 0.2rem 1.6rem; }

    .slot-item { display: flex; align-items: stretch; gap: 0.5rem; margin-bottom: 0.5rem; }
    .slot-item-action { display: flex; align-items: center; }
    .slot-row {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        flex-grow: 1;
        min-width: 0;
        padding: 0.7rem 0.9rem;
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        background: var(--surface);
        color: var(--text);
        text-align: left;
        transition: border-color 0.15s ease, transform 0.15s ease;
    }
    .slot-row:hover { border-color: var(--primary-color); transform: translateX(2px); }
    .slot-time { font-weight: 700; font-size: 0.9rem; line-height: 1.3; min-width: 9rem; }
    .slot-empty { padding: 2rem 1rem; text-align: center; color: var(--text-muted); }

    @media (max-width: 575.98px) {
        .slot-group-body { padding-left: 0.4rem; }
        .slot-row { flex-wrap: wrap; gap: 0.5rem; }
        .slot-time { min-width: 100%; }
    }
</style>
@endpush

@section('content')
<div class="d-sm-flex align-items-start justify-content-between mb-3">
    <div>
        <h1 class="h3 mb-1 text-gray-800 fw-bold">Jadwal & Replacement Class</h1>
        <p class="text-muted small mb-0">Proses permintaan kelas pengganti, lalu atur ketersediaan slot yang bisa dipakai sebagai kelas pengganti.</p>
    </div>
    <div class="d-flex gap-2 mt-2 mt-sm-0">
        <a href="{{ route('schedules.calendar') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-calendar3 me-1"></i>Lihat Kalender</a>
        <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg me-1"></i>Ajukan Replacement</a>
    </div>
</div>

{{-- ── Ringkasan ──────────────────────────────────────────────────
     Tiap kartu membuka panel yang mengelolanya — angka yang menarik perhatian
     dan tempat menindaklanjutinya jadi satu klik. --}}
@php
    // Nol pengajuan bukan angka yang perlu diteriaki. Kartunya berganti nada:
    // amber selama ada yang menunggu, hijau tenang begitu bersih — supaya warna
    // di layar ini selalu berarti "ada yang harus dikerjakan", bukan sekadar
    // penanda kartu mana yang mana.
    $adaPending = $pendingCount > 0;

    // Slot: hijau selama masih ada yang bisa dipakai, merah bila kelas ada tapi
    // tak satu pun bisa dipilih (itu keadaan yang perlu ditindak), kelabu bila
    // memang belum ada kelas sama sekali — belum diisi bukan kesalahan.
    $slotColor = $totalSlots === 0
        ? 'var(--stat-neutral)'
        : ($availableSlots > 0 ? 'var(--stat-success)' : 'var(--stat-danger)');
    $slotPersen = $totalSlots > 0 ? round($availableSlots / $totalSlots * 100) : 0;
@endphp
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <a href="{{ route('schedules.index', ['status' => 'pending']) }}" class="stat-card"
            style="--stat-color: {{ $adaPending ? 'var(--stat-warning)' : 'var(--stat-success)' }};"
            aria-label="{{ $adaPending ? $pendingCount.' request replacement menunggu persetujuan. Buka daftarnya.' : 'Tidak ada request replacement yang menunggu. Buka riwayatnya.' }}">
            <span class="stat-head">
                <span class="stat-icon"><i class="bi {{ $adaPending ? 'bi-hourglass-split' : 'bi-check2-circle' }}" aria-hidden="true"></i></span>
                <span class="stat-label">Replacement Pending</span>
            </span>
            <span class="stat-value">{{ $pendingCount }}</span>
            <span class="stat-foot">
                <span class="stat-note">{{ $adaPending ? 'menunggu persetujuan' : 'semua request sudah ditinjau' }}</span>
                <span class="stat-action">{{ $adaPending ? 'Tinjau' : 'Lihat daftar' }}<i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </span>
        </a>
    </div>

    <div class="col-md-6">
        <a href="{{ route('schedules.index', ['tab' => 'slots']) }}" class="stat-card"
            style="--stat-color: {{ $slotColor }};"
            aria-label="{{ $availableSlots }} dari {{ $totalSlots }} slot kelas bisa dipakai sebagai kelas pengganti. Buka pengaturan slot.">
            <span class="stat-head">
                <span class="stat-icon"><i class="bi {{ $availableSlots > 0 ? 'bi-check-circle' : 'bi-slash-circle' }}" aria-hidden="true"></i></span>
                <span class="stat-label">Slot Tersedia</span>
            </span>
            <span class="stat-value">{{ $availableSlots }}<span class="stat-total">/ {{ $totalSlots }}</span></span>
            <span class="stat-meter" aria-hidden="true">
                <span class="stat-meter-fill" style="width: {{ $slotPersen }}%;"></span>
            </span>
            <span class="stat-foot">
                <span class="stat-note">
                    @if($totalSlots === 0)
                        belum ada kelas terdaftar
                    @elseif($availableSlots > 0)
                        bisa dipilih untuk kelas pengganti
                    @else
                        tak ada slot yang bisa dipakai
                    @endif
                </span>
                <span class="stat-action">Atur slot<i class="bi bi-arrow-right" aria-hidden="true"></i></span>
            </span>
        </a>
    </div>
</div>

{{-- Switch panel: satu halaman, dua pekerjaan yang berdiri sendiri. Ditumpuk
     vertikal seperti sebelumnya, panel slot praktis tak pernah terlihat tanpa
     menggulir jauh. --}}
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
                                    {{ $req->originClass->class_category }}
                                    <br><small class="text-muted">{{ $req->originClass->scheduleLabel() }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <i class="bi bi-arrow-right-short text-success"></i>{{ $req->classRoom->class_category ?? '-' }}
                                @if($req->classRoom)<br><small class="text-muted">{{ $req->classRoom->scheduleLabel() }}</small>@endif
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
             keluaran availability(). --}}
        <div class="d-flex flex-wrap gap-3 small mb-3 pb-3 border-bottom">
            <span><span class="badge bg-success">Tersedia</span> bisa dipilih</span>
            <span><span class="badge bg-danger">Penuh</span> kuota habis</span>
            <span><span class="badge bg-warning">Tutor kosong</span> tutor nonaktif</span>
            <span><span class="badge bg-dark">Sudah lewat</span> tak punya sesi mendatang</span>
            <span><span class="badge bg-secondary">Ditutup</span> ditutup admin</span>
        </div>
        {{-- Dikelompokkan per kategori: satu kategori dibuka, seluruh jadwalnya
             terlihat sekaligus. Sedang menyaring berarti daftarnya sudah pendek
             dan memang ingin dilihat, jadi semuanya dibuka; begitu juga bila
             kategorinya cuma satu — menyembunyikan satu-satunya isi halaman
             hanya menambah satu klik tanpa merapikan apa pun. --}}
        @php $bukaSemua = $slotSearch !== '' || $slotStatus !== '' || $slotGroups->count() <= 1; @endphp
        <div class="slot-groups">
            @forelse($slotGroups as $group)
                @php $groupId = 'slotGroup'.$loop->index; @endphp
                <div class="slot-group">
                    <button type="button" class="slot-group-head @if(! $bukaSemua) collapsed @endif"
                        data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}"
                        aria-expanded="{{ $bukaSemua ? 'true' : 'false' }}" aria-controls="{{ $groupId }}">
                        <i class="bi bi-chevron-right slot-chevron"></i>
                        <span class="slot-group-name text-capitalize">{{ $group['label'] }}</span>
                        <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                            {{ $group['total'] }} jadwal
                        </span>
                        @if($group['seats'] > 0)
                            <span class="badge rounded-pill text-white" style="background-color:#15803D;">{{ $group['seats'] }} kursi tersisa</span>
                        @else
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">Tak ada kursi</span>
                        @endif
                    </button>

                    <div class="collapse @if($bukaSemua) show @endif" id="{{ $groupId }}">
                        <div class="slot-group-body">
                            @foreach($group['slots'] as $slot)
                                @php
                                    $av = $slot->availability();
                                    $nextSession = $slot->nextOccurrence();
                                @endphp
                                <div class="slot-item">
                                    {{-- Baris kelas dan tombol tutup/buka bersebelahan, bukan
                                         bersarang: tombol di dalam tombol bukan HTML yang sah,
                                         dan formnya butuh POST sendiri. --}}
                                    <button type="button" class="slot-row" data-class-id="{{ $slot->id }}">
                                        <span class="slot-time">
                                            {{ $slot->is_recurring ? 'Setiap '.$slot->dayName() : $slot->dayName().', '.$slot->schedule_date->format('d M Y') }}
                                            <br><span class="text-muted fw-normal">{{ $slot->timeRangeLabel() }}</span>
                                        </span>
                                        <span class="flex-grow-1">
                                            <span class="fw-semibold">{{ $slot->class_code }}</span>
                                            <span class="badge rounded-pill ms-1 bg-{{ $av['color'] }}">{{ $av['text'] }}</span>
                                            <br><span class="small text-muted">
                                                <i class="bi bi-person-video3 me-1"></i>{{ $slot->tutor->name ?? 'Tutor kosong' }}
                                                @if($slot->is_recurring)
                                                    &middot; {{ $nextSession ? 'Sesi berikutnya '.$nextSession->format('d M Y') : 'Belum ada sesi mendatang' }}
                                                @endif
                                            </span>
                                        </span>
                                        <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle text-nowrap">
                                            {{ $slot->enrolledCount() }} / {{ $slot->capacity }} murid
                                        </span>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </button>
                                    <div class="slot-item-action">
                                        @if($slot->isClosed())
                                            <form action="{{ route('classes.toggle-status', $slot) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button class="btn btn-sm btn-outline-success" title="Buka slot"><i class="bi bi-unlock me-1"></i>Buka</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-sm btn-outline-secondary btn-close-slot"
                                                data-action="{{ route('classes.toggle-status', $slot) }}"
                                                data-name="{{ $slot->class_category }} — {{ $slot->scheduleLabel() }}"
                                                title="Tutup slot"><i class="bi bi-lock me-1"></i>Tutup</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                    {{ ($slotSearch !== '' || $slotStatus !== '') ? 'Tidak ada slot yang cocok dengan filter.' : 'Belum ada kelas.' }}
                </div>
            @endforelse
        </div>
    </div>
</div>

</div>{{-- /panelSlots --}}

{{-- Pop-up detail slot: tutor & murid yang benar-benar terdaftar di kelas itu.
     Isinya dibaca dari roster yang sama dengan kalender, jadi angka terisi di
     sini, di kalender, dan di badge baris slot tak bisa berbeda-beda. --}}
<div class="modal fade" id="slotDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0 text-capitalize" id="slotDetailTitle">Detail Kelas</h5>
                    <small class="text-muted" id="slotDetailSubtitle"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="slotDetailBody"></div>
            <div class="modal-footer">
                {{-- Pintu ke pendaftaran anak: slot yang sedang dilihat ikut
                     terbawa, jadi anaknya masuk ke jadwal ini — bukan ke kelas
                     lain sekategori yang kebetulan dipilihkan sistem. --}}
                <a href="#" id="slotDetailEnroll" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Tambah Anak ke Slot Ini</a>
                <a href="#" id="slotDetailEdit" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Ubah Jadwal Kelas</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
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
    // ── Switch panel: Request Replacement / Ketersediaan Slot ──
    (function () {
        const PANELS = [
            { tab: 'requests', panel: 'panelRequests', toggle: 'toggleRequests' },
            { tab: 'slots', panel: 'panelSlots', toggle: 'toggleSlots' },
        ];

        function applyPanel(tab) {
            PANELS.forEach(function (p) {
                const el = document.getElementById(p.panel);
                if (el) el.style.display = p.tab === tab ? '' : 'none';
            });

            // Simpan tab di URL agar bertahan saat reload dan setelah submit filter.
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

    // ── Pop-up detail slot: tutor & murid satu kelas ──
    (function () {
        const rosters = @json($rosters ?? []);
        const detailEl = document.getElementById('slotDetailModal');
        if (!detailEl) return;

        const detail = new bootstrap.Modal(detailEl);
        const judul = document.getElementById('slotDetailTitle');
        const subJudul = document.getElementById('slotDetailSubtitle');
        const isi = document.getElementById('slotDetailBody');
        const tombolDaftar = document.getElementById('slotDetailEnroll');
        const tombolUbah = document.getElementById('slotDetailEdit');

        function escapeHtml(teks) {
            return String(teks ?? '').replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function buka(classId) {
            const roster = rosters[classId];
            if (!roster) return;

            judul.textContent = roster.category;
            subJudul.textContent = roster.code + ' · ' + roster.schedule;

            let html =
                `<div class="row g-3 mb-3">
                    <div class="col-sm-5">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Tutor</div>
                            <div class="fw-semibold">${escapeHtml(roster.tutor || 'Belum ada tutor')}</div>
                            ${roster.tutorPhone ? `<div class="small text-muted">${escapeHtml(roster.tutorPhone)}</div>` : ''}
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Jam</div>
                            <div class="fw-semibold">${escapeHtml(roster.time)}</div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Terisi</div>
                            <div class="fw-semibold">${roster.enrolled} / ${roster.capacity}</div>
                            <span class="badge bg-${escapeHtml(roster.availabilityColor)}">${escapeHtml(roster.availability)}</span>
                        </div>
                    </div>
                </div>`;

            if (roster.nextSession) {
                html += `<p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i>Sesi berikutnya ${escapeHtml(roster.nextSession)}.</p>`;
            }

            if (!roster.students.length) {
                html += '<div class="slot-empty"><i class="bi bi-people fs-3 d-block mb-2"></i>Belum ada murid di kelas ini.</div>';
            } else {
                html +=
                    `<div class="fw-semibold mb-2"><i class="bi bi-people me-1"></i>Murid terdaftar (${roster.students.length})</div>
                     <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                     <thead class="text-muted small text-uppercase"><tr><th>Nama</th><th>ID</th><th>Usia</th><th>Wali</th><th class="text-end">Data</th></tr></thead><tbody>` +
                    roster.students.map(function (murid) {
                        return `<tr class="slot-murid" data-url="${escapeHtml(murid.url)}" style="cursor:pointer;">
                            <td class="fw-semibold">${escapeHtml(murid.name)}</td>
                            <td class="small text-muted">${escapeHtml(murid.studentId)}</td>
                            <td class="small">${murid.age ? escapeHtml(murid.age) + ' th' : '-'}</td>
                            <td class="small">${escapeHtml(murid.parent || '-')}</td>
                            <td class="text-end"><i class="bi bi-pencil-square text-primary"></i></td>
                        </tr>`;
                    }).join('') +
                    '</tbody></table></div>';
            }

            isi.innerHTML = html;
            isi.querySelectorAll('.slot-murid').forEach(function (baris) {
                baris.addEventListener('click', function () { window.location = baris.dataset.url; });
            });

            // Slot penuh/ditutup tetap bisa dibuka detailnya — yang ditutup hanya
            // jalan pintas mendaftarkan anak ke dalamnya.
            tombolDaftar.href = roster.enrollUrl;
            tombolDaftar.classList.toggle('disabled', !roster.available);
            tombolDaftar.title = roster.available ? '' : 'Slot ini tidak menerima murid baru: ' + roster.availability;
            tombolUbah.href = roster.editUrl;

            detail.show();
        }

        document.querySelectorAll('.slot-row').forEach(function (baris) {
            baris.addEventListener('click', function () { buka(baris.dataset.classId); });
        });
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
