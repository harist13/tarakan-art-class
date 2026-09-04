@extends('layouts.app')

@section('title', 'Detail raport')

@push('styles')
<style>
    .report-card { overflow: hidden; }
    .report-hero {
        background: linear-gradient(135deg, #0EA5E9 0%, #2563EB 100%);
        padding: 1.75rem; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; color: #fff;
    }
    .hero-avatar {
        width: 84px; height: 84px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.6); font-size: 2rem; font-weight: 800; color: #fff; flex-shrink: 0;
    }
    .hero-main { flex: 1 1 180px; min-width: 0; }
    .hero-main h4 { font-weight: 800; margin: 0 0 .15rem; }
    .hero-sub { color: rgba(255,255,255,0.85); font-size: .85rem; }
    .hero-meta { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
    .class-chip {
        display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .8rem; border-radius: 30px;
        background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.35);
        font-size: .78rem; font-weight: 700; letter-spacing: .3px;
    }
    .report-section { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); }
    .report-section h6 {
        font-weight: 800; text-transform: uppercase; letter-spacing: .5px; font-size: .75rem;
        color: var(--primary-color); display: flex; align-items: center; gap: .5rem; margin-bottom: .6rem;
    }
    .report-section p { margin: 0; white-space: pre-line; line-height: 1.7; }
    .credential-box {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
        padding: .85rem 1.25rem; border-radius: .75rem;
        background: rgba(14,165,233,0.10); border: 1px dashed var(--primary-color);
    }
</style>
@endpush

@section('content')
@php
    $initial = strtoupper(mb_substr($report->student->name ?? '?', 0, 1));
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Detail raport</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="card report-card">
    <div class="report-hero">
        <div class="hero-avatar">{{ $initial }}</div>
        <div class="hero-main">
            <h4>{{ $report->student->name ?? '-' }}</h4>
            <div class="hero-sub">
                <i class="bi bi-person-badge me-1"></i>{{ $report->student->student_id ?? '-' }}
                <span class="mx-1">·</span>
                <i class="bi bi-calendar3 me-1"></i>{{ $report->period_start->format('d M Y') }} — {{ $report->period_end->format('d M Y') }}
            </div>
            <div class="hero-meta">
                @forelse($report->student->classes as $class)
                    <span class="class-chip"><i class="bi bi-easel2"></i>{{ $class->class_category }}</span>
                @empty
                    <span class="class-chip"><i class="bi bi-easel2"></i>Belum ada kelas</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="report-section">
        <div class="credential-box">
            <span class="fw-semibold"><i class="bi bi-key-fill text-primary me-2"></i>Credential key untuk orang tua</span>
            <code class="fs-5 fw-bold text-primary">{{ $report->credential_key }}</code>
        </div>
    </div>

    <div class="report-section">
        <h6><i class="bi bi-graph-up-arrow"></i> Catatan aktivitas / perkembangan</h6>
        <p>{{ $report->activity_notes }}</p>
    </div>

    @if($report->tutor_notes)
        <div class="report-section">
            <h6><i class="bi bi-chat-square-quote"></i> Catatan tutor</h6>
            <p>{{ $report->tutor_notes }}</p>
        </div>
    @endif

    {{-- Karya sepanjang periode raport — sama persis dengan yang dilihat orang
         tua lewat credential key. Unggahnya di modul Galeri karya. --}}
    <div class="report-section">
        <h6><i class="bi bi-images"></i> Karya periode ini ({{ $artworks->count() }})</h6>
        @if($artworks->isNotEmpty())
            <div class="row g-2">
                @foreach($artworks as $artwork)
                    <div class="col-4 col-md-3 col-lg-2">
                        <a href="{{ $artwork->photoUrl() }}" target="_blank" rel="noopener"
                           title="{{ $artwork->description ?: $artwork->taken_on->format('d M Y') }}">
                            <img src="{{ $artwork->photoUrl() }}" alt="{{ $artwork->description ?: 'Karya' }}"
                                 class="w-100 rounded border" style="height:100px; object-fit:cover;">
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <p class="small text-muted">Belum ada foto karya pada periode ini.</p>
        @endif
        @if($report->student)
            <a href="{{ route('artworks.folder', ['student' => $report->student, 'month' => $report->period_start->format('Y-m')]) }}"
               class="btn btn-sm btn-outline-primary mt-3">
                <i class="bi bi-folder2-open me-1"></i>Kelola folder karya
            </a>
        @endif
    </div>

    <div class="report-section d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted">
            <i class="bi bi-person me-1"></i>Dibuat oleh {{ $report->creator->full_name ?? '-' }}
            pada {{ $report->created_at->format('d M Y H:i') }}
        </span>
        <a href="{{ route('reports.guest') }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Buka halaman akses orang tua
        </a>
    </div>
</div>
@endsection
