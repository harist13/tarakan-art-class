@extends('layouts.public')

@section('title', 'Kelas Seni Anak di Tarakan')
@section('description', 'Kelas menggambar dan mewarnai untuk anak usia 3–12 tahun di Tarakan. Kelas kecil maksimal 8 anak, tutor berpengalaman, dan raport perkembangan tiap semester.')

@section('content')

{{-- ─── 1. Hero ────────────────────────────────────────────────── --}}
<section class="tac-bg-paper-2 position-relative overflow-hidden tac-section">
    <span class="tac-deco tac-float d-none d-lg-block" style="top: 4rem; left: 2rem;" aria-hidden="true">🖍️</span>
    <span class="tac-deco tac-float-slow d-none d-lg-block" style="bottom: 4rem; right: 3rem;" aria-hidden="true">✏️</span>

    <div class="container position-relative">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <p class="d-inline-flex align-items-center gap-2 bg-white tac-shadow-sm rounded-pill px-3 py-2 mb-4 fw-bold"
                   style="border: 2px solid var(--tac-ink); font-size: 0.8rem;">
                    <span class="tac-bg-leaf rounded-circle d-inline-block" style="width: 0.5rem; height: 0.5rem;" aria-hidden="true"></span>
                    Pendaftaran kelas dibuka
                </p>

                <h1 class="display-4 lh-1 mb-0">
                    Tempat anak berani mencoret dunianya
                </h1>

                <p class="fs-5 lh-lg tac-muted mt-4 mb-0" style="max-width: 34rem;">
                    Kelas menggambar &amp; mewarnai untuk anak usia 3–12 tahun di Tarakan.
                    Kelas kecil, tutor yang sabar, dan ruang aman untuk bereksperimen —
                    tanpa takut salah.
                </p>

                <div class="d-flex flex-column flex-sm-row gap-3 mt-4">
                    <x-site.btn :href="route('public.contact')" variant="coral" size="lg">Daftar Sekarang</x-site.btn>
                    <x-site.btn :href="route('public.programs')" variant="ghost" size="lg">Lihat Program</x-site.btn>
                </div>

                <dl class="row g-4 mt-4 mb-0" style="max-width: 28rem;">
                    @foreach(collect($stats)->take(3) as $stat)
                        <div class="col-4">
                            <dt class="visually-hidden">{{ $stat['label'] }}</dt>
                            <dd class="mb-0">
                                <span class="d-block tac-display fs-2 fw-bolder">{{ $stat['value'] }}</span>
                                <span class="d-block tac-muted" style="font-size: 0.75rem;">{{ $stat['label'] }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Ilustrasi alat lukis — inline SVG, tanpa request gambar tambahan. --}}
            <div class="col-lg-6">
                <div class="tac-card tac-hero-visual p-3 p-sm-4 mx-auto" style="max-width: 29rem;">
                    @include('partials.site-hero-art')

                    <div class="d-flex flex-wrap justify-content-center gap-2 pb-2">
                        <span class="tac-pill-stat">
                            <span class="tac-bg-coral rounded-circle" style="width: 0.5rem; height: 0.5rem;" aria-hidden="true"></span>
                            Maks. 8 anak / kelas
                        </span>
                        <span class="tac-pill-stat">
                            <span class="tac-bg-leaf rounded-circle" style="width: 0.5rem; height: 0.5rem;" aria-hidden="true"></span>
                            Alat &amp; bahan disediakan
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─── 2. Program unggulan ────────────────────────────────────── --}}
<x-site.section tone="paper">
    <x-site.heading
        eyebrow="Program"
        title="Kelas yang tumbuh bersama anak"
        subtitle="Empat program dengan materi bertingkat, dari mengenal warna sampai ilustrasi." />

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 mt-3">
        @foreach($programs as $program)
            <div class="col"><x-site.program-card :program="$program" /></div>
        @endforeach
    </div>

    <div class="text-center mt-5">
        <x-site.btn :href="route('public.programs')" variant="ghost">Lihat detail semua kelas</x-site.btn>
    </div>
</x-site.section>

{{-- ─── 3. Kenapa Tarakan Art Class ────────────────────────────── --}}
<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="Kenapa Kami"
        title="Bukan sekadar tempat menitipkan anak"
        subtitle="Kami merancang kelas supaya anak pulang membawa keterampilan baru, bukan cuma lembar mewarnai." />

    @php
        $reasons = [
            ['icon' => '👩‍🎨', 'title' => 'Tutor berpengalaman', 'body' => 'Tim tutor dengan latar seni rupa dan PAUD yang terbiasa mengajar anak.', 'bg' => 'tac-bg-coral tac-text-paper'],
            ['icon' => '🧒', 'title' => 'Kelas kecil', 'body' => 'Maksimal 8 anak per kelas agar setiap anak dapat perhatian personal.', 'bg' => 'tac-bg-sun'],
            ['icon' => '📘', 'title' => 'Materi bertingkat', 'body' => 'Kurikulum naik bertahap sesuai usia dan kemampuan awal anak.', 'bg' => 'tac-bg-sky tac-text-paper'],
            ['icon' => '📝', 'title' => 'Raport perkembangan', 'body' => 'Orang tua menerima catatan kemajuan anak berikut dokumentasi karyanya.', 'bg' => 'tac-bg-leaf'],
            ['icon' => '🔁', 'title' => 'Kelas pengganti', 'body' => 'Anak berhalangan hadir? Ada slot replacement tanpa biaya tambahan.', 'bg' => 'tac-bg-grape tac-text-paper'],
            ['icon' => '🎒', 'title' => 'Alat disediakan', 'body' => 'Semua bahan sudah termasuk biaya bulanan. Anak cukup datang.', 'bg' => 'tac-bg-coral tac-text-paper'],
        ];
    @endphp

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mt-3">
        @foreach($reasons as $reason)
            <div class="col">
                <div class="tac-card tac-card-hover h-100 p-4">
                    <span class="tac-icon {{ $reason['bg'] }}" aria-hidden="true">{{ $reason['icon'] }}</span>
                    <h3 class="fs-5 mt-3 mb-2">{{ $reason['title'] }}</h3>
                    <p class="small lh-lg tac-muted mb-0">{{ $reason['body'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-site.section>

{{-- ─── 4. Preview galeri ──────────────────────────────────────── --}}
<x-site.section tone="paper">
    <x-site.heading
        eyebrow="Galeri"
        title="Karya-karya kecil yang membanggakan"
        subtitle="Sebagian hasil karya murid dari kelas mingguan dan Holiday Class." />

    @if($galleryPreview->isNotEmpty())
        <div class="row row-cols-2 row-cols-sm-3 g-4 mt-3">
            @foreach($galleryPreview as $item)
                <div class="col">
                    <figure class="tac-card overflow-hidden h-100 mb-0">
                        <img src="{{ $item['url'] }}" alt="{{ $item['caption'] ?: 'Karya murid Tarakan Art Class' }}"
                             loading="lazy" decoding="async" class="tac-thumb">
                        @if($item['caption'])
                            <figcaption class="px-3 py-2 tac-muted" style="font-size: 0.75rem;">{{ $item['caption'] }}</figcaption>
                        @endif
                    </figure>
                </div>
            @endforeach
        </div>
    @else
        {{-- Belum ada foto yang didaftarkan di config/site.php → arahkan ke Instagram. --}}
        <div class="row row-cols-2 row-cols-sm-3 g-4 mt-3" aria-hidden="true">
            @foreach(['tac-bg-coral-soft','tac-bg-sun-soft','tac-bg-sky-soft','tac-bg-leaf-soft','tac-bg-grape-soft','tac-bg-sun-soft'] as $tone)
                <div class="col">
                    <div class="tac-card {{ $tone }} tac-thumb tac-thumb-placeholder">🖼️</div>
                </div>
            @endforeach
        </div>
        <p class="text-center small tac-muted mt-4 mb-0">
            Dokumentasi terbaru kami unggah lebih dulu di Instagram
            <a class="fw-semibold tac-text-coral" href="https://instagram.com/{{ config('site.contact.instagram') }}" target="_blank" rel="noopener">
                &#64;{{ config('site.contact.instagram') }}
            </a>.
        </p>
    @endif

    <div class="text-center mt-5">
        <x-site.btn :href="route('public.gallery')" variant="ghost">Buka galeri lengkap</x-site.btn>
    </div>
</x-site.section>

{{-- ─── 5. Testimoni ───────────────────────────────────────────── --}}
<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="Testimoni"
        title="Kata orang tua murid"
        subtitle="Alasan mereka mempercayakan waktu bermain anaknya pada kami." />

    <div class="row row-cols-1 row-cols-md-3 g-4 mt-3">
        @foreach($testimonials as $testimonial)
            <div class="col">
                <figure class="tac-card tac-card-hover h-100 d-flex flex-column p-4 mb-0">
                    <span class="tac-display fs-1 lh-1 tac-text-sun" aria-hidden="true">&ldquo;</span>
                    <blockquote class="small lh-lg tac-muted flex-grow-1 mt-2 mb-0">
                        {{ $testimonial['quote'] }}
                    </blockquote>
                    <figcaption class="tac-dashed-top d-flex align-items-center gap-3 mt-4 pt-3">
                        <span class="tac-icon tac-icon-sm tac-bg-paper-2 rounded-circle tac-display fw-bolder" aria-hidden="true">
                            {{ mb_substr($testimonial['name'], 0, 1) }}
                        </span>
                        <span>
                            <span class="d-block tac-display fw-bold" style="font-size: 0.9rem;">{{ $testimonial['name'] }}</span>
                            <span class="d-block tac-muted-soft" style="font-size: 0.75rem;">{{ $testimonial['role'] }}</span>
                        </span>
                    </figcaption>
                </figure>
            </div>
        @endforeach
    </div>
</x-site.section>

{{-- ─── 6. CTA penutup ─────────────────────────────────────────── --}}
<x-site.cta />

@endsection
