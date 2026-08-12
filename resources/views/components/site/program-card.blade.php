@props([
    'program',
    'detailed' => false,   // true = tampilkan harga, kapasitas, dan poin materi
])

@php
    $accents = [
        'sun' => ['chip' => 'tac-bg-sun tac-text-ink', 'bar' => 'tac-bg-sun'],
        'coral' => ['chip' => 'tac-bg-coral tac-text-paper', 'bar' => 'tac-bg-coral'],
        'sky' => ['chip' => 'tac-bg-sky tac-text-paper', 'bar' => 'tac-bg-sky'],
        'leaf' => ['chip' => 'tac-bg-leaf tac-text-ink', 'bar' => 'tac-bg-leaf'],
        'grape' => ['chip' => 'tac-bg-grape tac-text-paper', 'bar' => 'tac-bg-grape'],
    ];
    $accent = $accents[$program['color'] ?? 'coral'] ?? $accents['coral'];

    $icons = ['sparkle' => '✨', 'palette' => '🎨', 'pencil' => '✏️', 'sun' => '🌞'];
    $icon = $icons[$program['icon'] ?? ''] ?? '🎨';

    $next = $program['next_class'] ?? null;
    // Sesi Holiday Class terdekat — jadwal, kapasitas, & biaya di atas sudah ikut
    // diisi dari sesi ini, yang tersisa hanyalah temanya (tidak ada di config).
    $nextHoliday = $program['next_holiday'] ?? null;
@endphp

<article {{ $attributes->class('tac-card tac-card-hover h-100 d-flex flex-column overflow-hidden') }}>
    <div class="{{ $accent['bar'] }}" style="height: 0.5rem;"></div>

    <div class="p-4 d-flex flex-column flex-grow-1">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <span class="tac-icon {{ $accent['chip'] }}" aria-hidden="true">{{ $icon }}</span>
            <span class="tac-badge tac-badge-outline">{{ $program['age'] }}</span>
        </div>

        <h3 class="fs-5 mt-3 mb-2">{{ $program['name'] }}</h3>
        <p class="small lh-lg tac-muted mb-0">{{ $program['summary'] }}</p>

        @if($detailed && ! empty($program['highlights']))
            <ul class="list-unstyled d-grid gap-2 mt-3 mb-0 small tac-muted">
                @foreach($program['highlights'] as $highlight)
                    <li class="d-flex align-items-start gap-2">
                        <span class="{{ $accent['bar'] }} rounded-circle flex-shrink-0 mt-2"
                              style="width: 0.4rem; height: 0.4rem;" aria-hidden="true"></span>
                        <span>{{ $highlight }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <dl class="tac-dashed-top mt-4 pt-3 mb-0 small d-grid gap-2">
            <div class="d-flex justify-content-between gap-3">
                <dt class="fw-normal tac-muted-soft">Durasi</dt>
                <dd class="mb-0 text-end fw-semibold">{{ $program['duration'] }}</dd>
            </div>
            @if($detailed)
                <div class="d-flex justify-content-between gap-3">
                    <dt class="fw-normal tac-muted-soft">Kapasitas</dt>
                    <dd class="mb-0 text-end fw-semibold">{{ $program['capacity'] }}</dd>
                </div>
            @endif
            <div class="d-flex justify-content-between gap-3">
                <dt class="fw-normal tac-muted-soft">Jadwal</dt>
                <dd class="mb-0 text-end fw-semibold">{{ $program['schedule_hint'] }}</dd>
            </div>
            <div class="d-flex justify-content-between gap-3">
                <dt class="fw-normal tac-muted-soft">Biaya</dt>
                <dd class="mb-0 text-end tac-display fw-bolder fs-6">{{ $program['price'] }}</dd>
            </div>
        </dl>

        {{-- Data live dari sistem: slot terdekat yang masih dibuka. --}}
        @if($next)
            <p class="d-flex flex-wrap align-items-center gap-2 tac-bg-paper-2 rounded-3 px-3 py-2 mt-3 mb-0"
               style="font-size: 0.75rem;">
                <span class="fw-semibold">Kelas terdekat:</span>
                <span class="tac-muted">{{ $next->schedule_date->format('d/m/Y') }}, {{ substr($next->schedule_time, 0, 5) }} WITA</span>
                @if($next->remainingSeats() > 0)
                    <span class="tac-badge tac-bg-leaf-soft">{{ $next->remainingSeats() }} kursi tersisa</span>
                @else
                    <span class="tac-badge tac-bg-coral-soft">Kelas penuh</span>
                @endif
            </p>
        @elseif($nextHoliday)
            <p class="d-flex flex-wrap align-items-center gap-2 tac-bg-paper-2 rounded-3 px-3 py-2 mt-3 mb-0"
               style="font-size: 0.75rem;">
                <span class="fw-semibold">Tema sesi terdekat:</span>
                <span class="tac-muted">{{ $nextHoliday->class_name }}</span>
                <span class="tac-badge tac-bg-leaf-soft">{{ $nextHoliday->capacity }} kursi</span>
            </p>
        @endif

        <div class="mt-auto pt-4">
            <x-site.btn :href="route('public.contact', ['kelas' => $program['slug']])" size="sm" class="w-100">
                Daftar kelas ini
            </x-site.btn>
        </div>
    </div>
</article>
