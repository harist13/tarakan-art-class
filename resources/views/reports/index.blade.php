@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Raport Siswa</h1>
        @if(isset($month))
            @php
                $dt = \Carbon\Carbon::createFromFormat('Y-m', $month);
            @endphp
            <nav aria-label="breadcrumb" class="mt-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Semua Bulan</a></li>
                    <li class="breadcrumb-item active">{{ $dt->translatedFormat('F Y') }}</li>
                </ol>
            </nav>
        @endif
    </div>
    <a href="{{ route('reports.create', isset($month) ? ['month' => $month] : []) }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Buat Raport</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold">{{ isset($month) ? $dt->translatedFormat('F Y') . ' — ' . $reports->count() . ' raport' : 'Daftar Raport per Bulan' }}</span>
        @if(isset($month))
            <form method="GET" data-live class="d-flex" style="max-width:320px;">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari murid / credential key...">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </form>
        @endif
    </div>
    <div class="card-body">
        @include('partials.arrears-note', [
            'count' => $withheldCount,
            'label' => 'raport',
            'effect' => 'Orang tuanya belum bisa membukanya lewat credential key sampai tunggakan itu lunas.',
        ])

        @if(isset($month))
            {{-- Mode detail bulan: tabel raport per siswa --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr><th>Credential Key</th><th>Murid</th><th>Periode</th><th>Dibuat oleh</th><th class="text-end">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td><code>{{ $report->credential_key }}</code></td>
                                <td class="fw-bold">{{ $report->student->name ?? '-' }}</td>
                                <td class="small">{{ $report->period_start->format('d M Y') }} — {{ $report->period_end->format('d M Y') }}</td>
                                <td class="small">{{ $report->creator->full_name ?? '-' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-outline-primary" title="Lihat raport"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-info text-white" title="Edit raport"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('reports.destroy', $report) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus raport ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Hapus raport"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada raport di bulan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            {{-- Mode default: daftar folder per bulan --}}
            <div class="row g-3">
                @forelse($months as $item)
                    @php $dt = \Carbon\Carbon::createFromFormat('Y-m', $item->month); @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('reports.index', ['month' => $item->month]) }}" class="text-decoration-none">
                            <div class="card border h-100 text-center py-4 px-3 folder-card">
                                <div class="mb-2"><i class="bi bi-folder-fill text-primary" style="font-size: 2.5rem;"></i></div>
                                <div class="fw-bold text-body">{{ $dt->translatedFormat('F Y') }}</div>
                                <small class="text-muted">{{ $item->total }} raport</small>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Belum ada raport.
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
@endsection
