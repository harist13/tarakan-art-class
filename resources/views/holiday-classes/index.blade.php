@extends('layouts.app')

@section('content')
@php
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $tanggalSingkat = fn ($date) => $date->format('j').' '.$bulan[(int) $date->format('n')].' '.$date->format('Y');
    $tanggalID = fn ($date) => $hari[(int) $date->format('w')].', '.$tanggalSingkat($date);
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Holiday Class</h1>
        <div class="small text-muted mt-1">
            <i class="bi bi-globe2 me-1"></i>
            Sesi mendatang otomatis tampil di
            <a href="{{ route('public.programs') }}" target="_blank" rel="noopener">halaman Program</a> dan
            <a href="{{ route('public.schedule') }}" target="_blank" rel="noopener">Jadwal</a> website.
        </div>
    </div>
    <a href="{{ route('holiday-classes.create') }}" class="btn btn-sm btn-primary shadow-sm">
        <i class="bi bi-plus-lg"></i> Jadwalkan Sesi
    </a>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-left-primary h-100 py-2"><div class="card-body">
            <div class="text-primary text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Sesi Mendatang</div>
            <div class="h5 fw-bold mb-0">{{ $upcomingCount }} sesi</div>
        </div></div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-success h-100 py-2"><div class="card-body">
            <div class="text-success text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Total Kursi Ditawarkan</div>
            <div class="h5 fw-bold mb-0">{{ $upcomingSeats }} kursi</div>
        </div></div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card border-left-warning h-100 py-2"><div class="card-body">
            <div class="text-warning text-uppercase mb-1" style="font-size:.7rem;font-weight:800;">Sesi Terdekat</div>
            <div class="h5 fw-bold mb-0">
                @if($next)
                    {{ $tanggalSingkat($next->schedule) }}
                    <span class="d-block small fw-normal text-muted text-truncate">{{ $next->class_name }}</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="fw-bold text-nowrap">Daftar Sesi</span>
        <form method="GET" data-live class="d-flex flex-wrap align-items-center gap-2">
            <div class="input-group input-group-sm" style="width:220px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0 py-2" placeholder="Cari nama sesi...">
            </div>
            <select name="filter" class="form-select form-select-sm" style="width:160px;">
                <option value="upcoming" @selected($filter === 'upcoming')>Mendatang</option>
                <option value="past" @selected($filter === 'past')>Sudah lewat</option>
                <option value="all" @selected($filter === 'all')>Semua</option>
            </select>
            @if($search !== '' || $filter !== 'upcoming')
                <a href="{{ route('holiday-classes.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset filter"><i class="bi bi-x-lg"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Nama Sesi</th><th>Jadwal</th><th>Kapasitas</th><th>Biaya</th><th>Status</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td class="fw-bold">{{ $class->class_name }}</td>
                            <td>
                                {{ $tanggalID($class->schedule) }}
                                <span class="d-block text-muted small">{{ $class->schedule->format('H.i') }} WITA</span>
                            </td>
                            <td>{{ $class->capacity }} anak</td>
                            <td>Rp {{ number_format($class->price, 0, ',', '.') }}</td>
                            <td>
                                @if($class->hasPassed())
                                    <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #475569;">Sudah lewat</span>
                                @else
                                    <span class="badge rounded-pill px-3 py-1 text-white fw-semibold" style="background-color: #15803D;">Tampil di website</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('holiday-classes.edit', $class) }}" class="btn btn-sm btn-info text-white"><i class="bi bi-pencil"></i></a>
                                <form action="{{ route('holiday-classes.destroy', $class) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus sesi Holiday Class ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            @if($search !== '')
                                Tidak ada sesi yang cocok dengan pencarian.
                            @elseif($filter === 'upcoming')
                                Belum ada sesi mendatang. Website masih menampilkan teks perkiraan
                                <em>"Musiman — libur sekolah"</em> sampai ada sesi dijadwalkan.
                            @else
                                Belum ada sesi Holiday Class.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $classes->links() }}
    </div>
</div>
@endsection
