@extends('layouts.public')

@section('title', 'Tentang kami')
@section('description', 'Cerita di balik Tarakan Art Class, visi & misi kami, metode belajar yang membedakan, dan profil tutor yang mendampingi anak setiap kelas.')

@section('content')

{{-- ─── Cerita ─────────────────────────────────────────────────── --}}
<x-site.section tone="paper-2" :paint="true">
    <div class="row align-items-center g-5">
        <div class="col-lg-6">
            <x-site.heading
                level="h1"
                align="left"
                eyebrow="Tentang kami"
                title="Studio kecil, ruang tumbuh yang besar" />
            <p class="lh-lg tac-muted mt-4 mb-0">{{ $about['story'] }}</p>

            <div class="d-flex flex-wrap gap-3 mt-4">
                <x-site.btn :href="route('public.programs')">Lihat program</x-site.btn>
                <x-site.btn :href="route('public.contact')" variant="ghost">Kunjungi studio</x-site.btn>
            </div>
        </div>

        <div class="col-lg-6">
            <dl class="row row-cols-2 g-4 mb-0">
                @php $tones = ['tac-bg-coral-soft', 'tac-bg-sun-soft', 'tac-bg-sky-soft', 'tac-bg-leaf-soft']; @endphp
                @foreach($about['stats'] as $i => $stat)
                    <div class="col">
                        <div class="tac-card {{ $tones[$i % 4] }} text-center p-4 h-100">
                            <dt class="visually-hidden">{{ $stat['label'] }}</dt>
                            <dd class="mb-0">
                                <span class="d-block tac-display display-6 fw-bolder">{{ $stat['value'] }}</span>
                                <span class="d-block tac-muted mt-1" style="font-size: 0.75rem;">{{ $stat['label'] }}</span>
                            </dd>
                        </div>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
</x-site.section>

{{-- ─── Visi & Misi ────────────────────────────────────────────── --}}
<x-site.section tone="paper">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="tac-card tac-bg-ink tac-text-paper h-100 p-4 p-sm-5">
                <span class="tac-icon tac-bg-sun border-0" aria-hidden="true">🎯</span>
                <h2 class="fs-3 tac-text-paper mt-4 mb-3">Visi</h2>
                <p class="lh-lg tac-muted-invert mb-0">{{ $about['vision'] }}</p>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="tac-card h-100 p-4 p-sm-5">
                <span class="tac-icon tac-bg-coral tac-text-paper" aria-hidden="true">🚀</span>
                <h2 class="fs-3 mt-4 mb-3">Misi</h2>
                <ul class="list-unstyled d-grid gap-3 mb-0">
                    @foreach($about['mission'] as $mission)
                        <li class="d-flex align-items-start gap-3 small lh-lg tac-muted">
                            <span class="tac-icon tac-icon-sm tac-bg-sun-soft rounded-circle tac-display fw-bold"
                                  style="width: 1.5rem; height: 1.5rem; font-size: 0.7rem;" aria-hidden="true">
                                {{ $loop->iteration }}
                            </span>
                            <span>{{ $mission }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-site.section>

{{-- ─── Metode belajar ─────────────────────────────────────────── --}}
<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="Metode"
        title="Yang membedakan kelas kami"
        subtitle="Empat prinsip yang kami pegang di setiap sesi kelas." />

    <div class="row row-cols-1 row-cols-sm-2 g-4 mt-3">
        @php $bars = ['tac-bg-coral', 'tac-bg-sun', 'tac-bg-sky', 'tac-bg-leaf']; @endphp
        @foreach($about['methods'] as $i => $method)
            <div class="col">
                <div class="tac-card tac-card-hover h-100 d-flex gap-4 p-4">
                    <span class="{{ $bars[$i % 4] }} rounded-pill flex-shrink-0" style="width: 0.375rem;" aria-hidden="true"></span>
                    <div>
                        <h3 class="fs-5 mb-2">{{ $method['title'] }}</h3>
                        <p class="small lh-lg tac-muted mb-0">{{ $method['body'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-site.section>

{{-- ─── Tutor ──────────────────────────────────────────────────── --}}
<x-site.section tone="paper">
    <x-site.heading
        eyebrow="Tim tutor"
        title="Orang-orang yang mendampingi anak Anda"
        subtitle="Tutor kami terbiasa mengajar anak, bukan sekadar mahir menggambar." />

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mt-3">
        @php $tones = ['tac-bg-coral-soft', 'tac-bg-sun-soft', 'tac-bg-sky-soft']; @endphp
        @foreach($about['tutors'] as $i => $tutor)
            <div class="col">
                <article class="tac-card tac-card-hover h-100 overflow-hidden text-center">
                    @if(! empty($tutor['photo']) && is_file(public_path('images/tutors/'.$tutor['photo'])))
                        <img src="{{ asset('images/tutors/'.$tutor['photo']) }}" alt="Foto {{ $tutor['name'] }}"
                             loading="lazy" decoding="async" class="tac-thumb-wide">
                    @else
                        <div class="{{ $tones[$i % 3] }} tac-thumb-wide tac-thumb-placeholder" aria-hidden="true">🧑‍🎨</div>
                    @endif

                    <div class="p-4">
                        <h3 class="fs-5 mb-1">{{ $tutor['name'] }}</h3>
                        <p class="tac-text-coral fw-semibold text-uppercase mb-0" style="font-size: 0.7rem; letter-spacing: 0.06em;">
                            {{ $tutor['role'] }}
                        </p>
                        <p class="small lh-lg tac-muted mt-3 mb-0">{{ $tutor['bio'] }}</p>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
</x-site.section>

{{-- ─── Fasilitas ──────────────────────────────────────────────── --}}
<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="Fasilitas"
        title="Studio yang aman dan nyaman"
        subtitle="Ruang kelas terang, meja setinggi anak, dan alat yang selalu siap pakai." />

    @php
        $facilities = [
            ['icon' => '🪑', 'label' => 'Meja & kursi ukuran anak'],
            ['icon' => '🧴', 'label' => 'Cat & alat non-toxic'],
            ['icon' => '💡', 'label' => 'Ruang terang & berventilasi'],
            ['icon' => '🧼', 'label' => 'Wastafel di dalam studio'],
            ['icon' => '🛋️', 'label' => 'Ruang tunggu orang tua'],
            ['icon' => '🖼️', 'label' => 'Dinding pameran karya'],
        ];
    @endphp

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 mt-3">
        @foreach($facilities as $facility)
            <div class="col">
                <div class="d-flex align-items-center gap-3 bg-white tac-shadow-sm px-4 py-3 h-100"
                     style="border: 1px solid var(--tac-line); border-radius: 1.1rem;">
                    <span class="fs-4" aria-hidden="true">{{ $facility['icon'] }}</span>
                    <span class="small fw-semibold">{{ $facility['label'] }}</span>
                </div>
            </div>
        @endforeach
    </div>
</x-site.section>

<x-site.cta
    title="Mau lihat langsung studionya?"
    subtitle="Kami dengan senang hati menerima kunjungan orang tua sebelum mendaftar. Atur jadwal kunjungan lewat WhatsApp." />

@endsection
