@extends('layouts.app')

@section('title', 'Detail Raport')

@push('styles')
<style>
    .report-card { overflow: hidden; }
    .report-hero {
        background: linear-gradient(135deg, #0EA5E9 0%, #2563EB 100%);
        padding: 1.75rem; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; color: #fff;
    }
    .hero-photo {
        width: 84px; height: 112px; border-radius: 0.75rem; object-fit: cover;
        border: 3px solid rgba(255,255,255,0.6); flex-shrink: 0; background: rgba(255,255,255,0.15);
    }
    .hero-avatar {
        width: 84px; height: 84px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,0.2); border: 3px solid rgba(255,255,255,0.6); font-size: 2rem; font-weight: 800; color: #fff; flex-shrink: 0;
    }
    .hero-main { flex: 1 1 180px; min-width: 0; }
    .hero-main h4 { font-weight: 800; margin: 0 0 .15rem; }
    .hero-sub { color: rgba(255,255,255,0.85); font-size: .85rem; }
    .hero-meta { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; }
    .grade-pill {
        display: inline-block; padding: .25rem .8rem; border-radius: 30px;
        background: rgba(255,255,255,0.22); font-size: .78rem; font-weight: 700; letter-spacing: .3px;
    }
    .class-chip {
        display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .8rem; border-radius: 30px;
        background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.35);
        font-size: .78rem; font-weight: 700; letter-spacing: .3px;
    }
    .score-ring {
        width: 96px; height: 96px; border-radius: 50%; flex-shrink: 0;
        background: conic-gradient(var(--ring-color) var(--deg), rgba(255,255,255,0.28) 0);
        display: grid; place-items: center;
    }
    .score-inner {
        width: 76px; height: 76px; border-radius: 50%; background: var(--surface);
        display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;
    }
    .score-num { font-size: 1.8rem; font-weight: 800; }
    .score-max { font-size: .7rem; color: var(--text-muted); margin-top: .1rem; }
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
    $score = $report->achievement_score;
    $grade = $score >= 90 ? 'Istimewa' : ($score >= 80 ? 'Sangat Baik' : ($score >= 70 ? 'Baik' : ($score >= 60 ? 'Cukup' : 'Perlu Bimbingan')));
    $initial = strtoupper(mb_substr($report->student->name ?? '?', 0, 1));
    $scoreColor = $score >= 70 ? '#22C55E' : ($score >= 60 ? '#EAB308' : '#EF4444');
@endphp

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Detail Raport</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-pencil me-1"></i> Edit</a>
        <a href="{{ route('reports.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<div class="card report-card">
    <div class="report-hero">
        @if($report->photoUrl())
            <img src="{{ $report->photoUrl() }}" alt="Foto {{ $report->student->name ?? '' }}" class="hero-photo">
        @else
            <div class="hero-avatar">{{ $initial }}</div>
        @endif
        <div class="hero-main">
            <h4>{{ $report->student->name ?? '-' }}</h4>
            <div class="hero-sub">
                <i class="bi bi-person-badge me-1"></i>{{ $report->student->student_id ?? '-' }}
                <span class="mx-1">·</span>
                <i class="bi bi-calendar3 me-1"></i>{{ $report->period_start->format('d M Y') }} — {{ $report->period_end->format('d M Y') }}
            </div>
            <div class="hero-meta">
                <span class="grade-pill"><i class="bi bi-award me-1"></i>{{ $grade }}</span>
                @forelse($report->student->classes as $class)
                    <span class="class-chip"><i class="bi bi-easel2"></i>{{ $class->class_name }}</span>
                @empty
                    <span class="class-chip"><i class="bi bi-easel2"></i>Belum ada kelas</span>
                @endforelse
            </div>
        </div>
        <div class="score-ring" style="--deg: {{ $score * 3.6 }}deg; --ring-color: {{ $scoreColor }};">
            <div class="score-inner">
                <span class="score-num" style="color: {{ $scoreColor }};">{{ $score }}</span>
                <span class="score-max">/ 100</span>
            </div>
        </div>
    </div>

    <div class="report-section">
        <div class="credential-box">
            <span class="fw-semibold"><i class="bi bi-key-fill text-primary me-2"></i>Credential Key untuk orang tua</span>
            <code class="fs-5 fw-bold text-primary">{{ $report->credential_key }}</code>
        </div>
    </div>

    <div class="report-section">
        <h6><i class="bi bi-graph-up-arrow"></i> Catatan Aktivitas / Perkembangan</h6>
        <p>{{ $report->activity_notes }}</p>
    </div>

    @if($report->tutor_notes)
        <div class="report-section">
            <h6><i class="bi bi-chat-square-quote"></i> Catatan Tutor</h6>
            <p>{{ $report->tutor_notes }}</p>
        </div>
    @endif

    <div class="report-section d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small text-muted">
            <i class="bi bi-person me-1"></i>Dibuat oleh {{ $report->creator->full_name ?? '-' }}
            pada {{ $report->created_at->format('d M Y H:i') }}
        </span>
        <a href="{{ route('reports.guest') }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Buka Halaman Akses Orang Tua
        </a>
    </div>
</div>
@endsection
