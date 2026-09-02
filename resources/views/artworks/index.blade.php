@extends('layouts.app')

@section('content')
@php
    $dt = isset($month) ? \Carbon\Carbon::createFromFormat('Y-m', $month) : null;
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Galeri Karya</h1>
        @if($dt)
            <nav aria-label="breadcrumb" class="mt-1">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="{{ route('artworks.index') }}">Semua Bulan</a></li>
                    <li class="breadcrumb-item active">{{ $dt->locale('id')->translatedFormat('F Y') }}</li>
                </ol>
            </nav>
        @else
            <p class="text-muted small mb-0 mt-1">Arsip foto karya murid, tersimpan per murid dan per bulan.</p>
        @endif
    </div>
    <a href="{{ route('artworks.create', $dt ? ['month' => $month] : []) }}" class="btn btn-sm btn-primary shadow-sm">
        <i class="bi bi-cloud-arrow-up me-1"></i>Tambah Karya
    </a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-bold">
            @if($dt)
                {{ $dt->locale('id')->translatedFormat('F Y') }} — {{ $folders->count() }} murid
            @else
                Daftar Folder per Bulan
            @endif
        </span>
        @if($dt)
            <form method="GET" data-live class="d-flex" style="max-width:320px;">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm me-2" placeholder="Cari nama murid...">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </form>
        @endif
    </div>
    <div class="card-body">
        @if($dt)
            {{-- Folder per murid: sampulnya karya terbaru murid itu di bulan ini. --}}
            <div class="row g-3">
                @forelse($folders as $folder)
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('artworks.folder', ['student' => $folder['student'], 'month' => $month]) }}" class="text-decoration-none">
                            <div class="card border h-100 folder-card overflow-hidden">
                                <img src="{{ $folder['cover']->photoUrl() }}"
                                     alt="Karya {{ $folder['student']->name }}"
                                     class="w-100" style="height:140px; object-fit:cover; background:#f1f5f9;">
                                <div class="p-3 text-center">
                                    <div class="fw-bold text-body text-truncate">{{ $folder['student']->name }}</div>
                                    <small class="text-muted">{{ $folder['total'] }} karya</small>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-4">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        {{ $search !== '' ? 'Tidak ada murid yang cocok dengan pencarian.' : 'Belum ada karya di bulan ini.' }}
                    </div>
                @endforelse
            </div>
        @else
            {{-- Folder bulan, sepola dengan Raport Siswa. --}}
            <div class="row g-3">
                @forelse($months as $item)
                    @php $bulan = \Carbon\Carbon::createFromFormat('Y-m', $item->month); @endphp
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route('artworks.index', ['month' => $item->month]) }}" class="text-decoration-none">
                            <div class="card border h-100 text-center py-4 px-3 folder-card">
                                <div class="mb-2"><i class="bi bi-folder-fill text-primary" style="font-size: 2.5rem;"></i></div>
                                <div class="fw-bold text-body">{{ $bulan->locale('id')->translatedFormat('F Y') }}</div>
                                <small class="text-muted">{{ $item->total }} karya · {{ $item->students }} murid</small>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted py-5">
                        <i class="bi bi-images fs-1 d-block mb-3 opacity-50"></i>
                        <p class="mb-3">Belum ada foto karya yang diarsipkan.</p>
                        <a href="{{ route('artworks.create') }}" class="btn btn-sm btn-primary">
                            <i class="bi bi-cloud-arrow-up me-1"></i>Tambah Karya Pertama
                        </a>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</div>
@endsection
