@extends('layouts.public')

@section('title', 'Kelas Seni Anak di Tarakan')
@section('description', 'Kelas menggambar dan mewarnai untuk anak usia 3–12 tahun di Tarakan. Kelas kecil maksimal 8 anak, tutor berpengalaman, dan raport perkembangan tiap semester.')

@section('content')

{{-- ─── 1. Hero ────────────────────────────────────────────────── --}}
<section class="tac-hero tac-paint-area position-relative overflow-hidden" data-tac-paint>

    {{-- Bulatan warna kabur di latar: memberi kehangatan tanpa file gambar. --}}
    <span class="tac-blob tac-bg-sun-soft" style="top: -6rem; left: -6rem; width: 24rem; height: 24rem; opacity: 0.4;" aria-hidden="true"></span>
    <span class="tac-blob tac-bg-sky-soft" style="top: 10rem; right: -5rem; width: 24rem; height: 24rem; opacity: 0.4;" aria-hidden="true"></span>

    {{-- Krayon di ujung kiri atas & kuas lukis di ujung kanan bawah. --}}
    <span class="tac-hero-deco tac-hero-deco-crayon tac-float" aria-hidden="true">
        {{-- Bentuk krayon klasik: sampul biru terang dengan lubang label lonjong,
             dua pita biru tua, serta ujung dan tutup pangkal biru sedang. --}}
        <svg viewBox="0 0 62 106" fill="none">
            <g transform="translate(50 100) rotate(-20)" stroke-linejoin="round">
                {{-- ujung runcing, satu warna rata dengan puncak sedikit tumpul --}}
                <path d="M-15 -22 -3 0H3L15 -22Z" fill="#5FA3D8"/>
                {{-- pita gelap bawah --}}
                <rect x="-15" y="-30" width="30" height="8" fill="#4E86B8"/>
                {{-- sampul --}}
                <rect x="-15" y="-82" width="30" height="52" fill="#8CC9F0"/>
                {{-- lubang label --}}
                <ellipse cx="0" cy="-56" rx="8" ry="18" fill="#FFFFFF"/>
                {{-- pita gelap atas --}}
                <rect x="-15" y="-90" width="30" height="8" fill="#4E86B8"/>
                {{-- tutup pangkal --}}
                <path d="M-15 -95Q-15 -98 -12 -98H12Q15 -98 15 -95V-90H-15Z" fill="#5FA3D8"/>
            </g>
        </svg>
    </span>

    <span class="tac-hero-deco tac-hero-deco-brush tac-float-slow" aria-hidden="true">
        <svg viewBox="0 0 60 120" fill="none">
            <g transform="translate(14 96) rotate(20)"
               stroke="#6B6670" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                {{-- gagang --}}
                <rect x="-9" y="-94" width="18" height="58" rx="9" fill="#F58C7A"/>
                {{-- cincin logam --}}
                <rect x="-11" y="-40" width="22" height="24" rx="5" fill="#EDF0F6"/>
                <path d="M-11 -28h22" stroke-width="2" opacity="0.3"/>
                {{-- bulu kuas --}}
                <path d="M-11 -18C-12 4 -6 14 0 22C6 14 12 4 11 -18Z" fill="#DD5843"/>
            </g>
        </svg>
    </span>

    {{-- Urutan tampil diatur z-index di CSS, bukan urutan markup ini. --}}
    @include('partials.site-paint-trail')

    <div class="container position-relative tac-section">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <p class="tac-rise tac-d1 tac-pill-stat mb-4">
                    <span class="position-relative d-inline-flex" style="width: 0.6rem; height: 0.6rem;" aria-hidden="true">
                        <span class="tac-ping tac-bg-leaf position-absolute top-0 start-0 w-100 h-100 rounded-circle opacity-75"></span>
                        <span class="position-relative w-100 h-100 rounded-circle" style="background-color: var(--tac-leaf-dark);"></span>
                    </span>
                    Pendaftaran kelas dibuka
                </p>

                <h1 class="tac-rise tac-d2 display-4 mb-0">
                    Tempat anak berani
                    <span class="tac-scribble tac-text-coral">mencoret<svg viewBox="0 0 300 24" preserveAspectRatio="none" fill="none" aria-hidden="true"><path d="M4 15 C 60 4, 120 4, 160 12 S 250 20, 296 8" stroke="var(--tac-sun)" stroke-width="7" stroke-linecap="round"/></svg></span>
                    dunianya
                </h1>

                <p class="tac-rise tac-d3 tac-hero-lead lh-lg tac-muted mt-4 mb-0">
                    Kelas menggambar &amp; mewarnai untuk anak usia 3–12 tahun di Tarakan.
                    Kelas kecil, tutor yang sabar, dan ruang aman untuk bereksperimen —
                    tanpa takut salah.
                </p>

                <div class="tac-rise tac-d4 d-flex flex-column flex-sm-row gap-3 mt-4">
                    <x-site.btn :href="route('public.contact')" variant="coral">
                        Daftar Sekarang
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6"/>
                        </svg>
                    </x-site.btn>
                    <x-site.btn :href="route('public.programs')" variant="ghost">Lihat Program</x-site.btn>
                </div>

                <dl class="tac-rise tac-d5 tac-hero-stats row g-4 mt-4 mb-0">
                    @foreach(collect($stats)->take(3) as $stat)
                        {{-- Angka dipisah dari imbuhannya ("300" + "+") supaya
                             imbuhan bisa diberi warna aksen seperti pada desain. --}}
                        @php preg_match('/^(\d*)(.*)$/u', $stat['value'], $parts); @endphp
                        <div class="col-4">
                            <dt class="visually-hidden">{{ $stat['label'] }}</dt>
                            <dd class="mb-0">
                                <span class="d-block tac-display tac-hero-stat-value fw-bolder lh-1">
                                    {{-- Angka dihitung naik dari 0 oleh JS saat blok terlihat.
                                         Tanpa JS, nilai akhir tetap tampil apa adanya. --}}
                                    <span class="tac-count" data-tac-count="{{ $parts[1] }}">{{ $parts[1] }}</span><span class="tac-text-coral">{{ $parts[2] }}</span>
                                </span>
                                <span class="d-block tac-muted mt-1" style="font-size: 0.8125rem;">{{ $stat['label'] }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Ilustrasi alat lukis — inline SVG, tanpa request gambar tambahan. --}}
            <div class="col-lg-6">
                <div class="tac-rise tac-d3 position-relative mx-auto" style="max-width: 26rem;">
                    <div class="tac-hero-visual p-3 p-sm-4">
                        @include('partials.site-hero-art')
                    </div>

                    {{-- Pil keterangan mengambang di tepi panel ilustrasi. --}}
                    {{-- Catatan: jangan tambahkan utilitas translate-* Bootstrap di sini —
                         transform-nya bentrok dengan animasi mengambang. --}}
                    <span class="tac-pill-stat tac-float-slow position-absolute start-0 ms-2 ms-sm-4" style="bottom: -0.75rem;">
                        <span class="tac-bg-coral rounded-circle" style="width: 0.5rem; height: 0.5rem;" aria-hidden="true"></span>
                        Maks. 8 anak / kelas
                    </span>
                    <span class="tac-pill-stat tac-float position-absolute end-0 me-2 me-sm-4" style="top: -1rem;">
                        <span class="rounded-circle" style="width: 0.5rem; height: 0.5rem; background-color: var(--tac-leaf-dark);" aria-hidden="true"></span>
                        Alat &amp; bahan disediakan
                    </span>
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
        subtitle="Pilihan program dengan materi bertingkat, dari pra-sekolah hingga menggambar." />

    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4 mt-3 justify-content-center">
        @foreach($programs as $program)
            <div class="col"><x-site.program-card :program="$program" /></div>
        @endforeach
    </div>

    <div class="text-center mt-5">
        <x-site.btn :href="route('public.programs')" variant="ghost">Lihat detail semua kelas</x-site.btn>
    </div>
</x-site.section>

{{-- ─── 3. Pengumuman & Agenda Studio ─────────────────────────── --}}
@php
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $tanggalID = fn ($date) => $hari[(int) $date->format('w')].', '.$date->format('j').' '.$bulan[(int) $date->format('n')].' '.$date->format('Y');
    $hasPengumuman = (isset($holidayClasses) && $holidayClasses->isNotEmpty())
        || (isset($announcements) && $announcements->isNotEmpty())
        || (isset($holidays) && $holidays->isNotEmpty());
@endphp

<x-site.section tone="paper-2">
    <x-site.heading
        eyebrow="Pengumuman"
        title="Pengumuman & Agenda Terkini"
        subtitle="Informasi kegiatan, sesi Holiday Class, jadwal libur, dan kabar terbaru dari Tarakan Art Class." />

    @if($hasPengumuman)
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3 justify-content-center">
            @if(isset($holidayClasses))
                @foreach($holidayClasses as $hc)
                    <div class="col">
                        <div class="tac-card tac-card-hover h-100 p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="tac-badge tac-bg-leaf-soft fw-bold">Holiday Class</span>
                                <span class="small tac-muted-soft">{{ $hc->schedule->format('H.i') }} WITA</span>
                            </div>
                            <h3 class="fs-5 mb-2">{{ $hc->class_name }}</h3>
                            <p class="small tac-muted-soft mb-3">
                                📅 {{ $tanggalID($hc->schedule) }}
                            </p>
                            <div class="tac-dashed-top mt-auto pt-3 d-flex justify-content-between align-items-center small">
                                <span class="tac-muted">{{ $hc->capacity }} anak / sesi</span>
                                <span class="tac-display fw-bolder tac-text-coral">Rp {{ number_format($hc->price, 0, ',', '.') }}</span>
                            </div>
                            <div class="mt-3">
                                <x-site.btn :href="route('public.contact', ['kelas' => 'holiday'])" size="sm" class="w-100">
                                    Daftar Holiday Class
                                </x-site.btn>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif

            @if(isset($announcements))
                @foreach($announcements as $event)
                    <div class="col">
                        <div class="tac-card tac-card-hover h-100 p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="tac-badge tac-bg-coral-soft fw-bold">Agenda & Pengumuman</span>
                                <span class="small tac-muted-soft">
                                    @if(!$event->isAllDay()) {{ substr($event->start_time, 0, 5) }} WITA @else Seharian @endif
                                </span>
                            </div>
                            <h3 class="fs-5 mb-2">{{ $event->title }}</h3>
                            <p class="small tac-muted-soft mb-2">
                                📅 {{ $tanggalID($event->date) }}
                            </p>
                            @if($event->description)
                                <p class="small lh-lg tac-muted mt-2 mb-0">{{ $event->description }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            @if(isset($holidays))
                @foreach($holidays as $holiday)
                    <div class="col">
                        <div class="tac-card tac-card-hover h-100 p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="tac-badge tac-bg-sun-soft fw-bold">Info Libur</span>
                                <span class="small tac-muted-soft">{{ $holiday->date->format('j') }} {{ $bulan[(int) $holiday->date->format('n')] }} {{ $holiday->date->format('Y') }}</span>
                            </div>
                            <h3 class="fs-5 mb-2">{{ $holiday->name ?: 'Kelas Ditiadakan' }}</h3>
                            <p class="small lh-lg tac-muted mb-0">
                                Studio dan kelas reguler ditiadakan pada tanggal ini. Sesi pengganti diatur sesuai kesepakatan.
                            </p>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @else
        <div class="tac-card text-center px-4 py-5 mt-4 mx-auto" style="max-width: 38rem;">
            <span class="tac-icon tac-bg-sun fs-4 mx-auto mb-3" aria-hidden="true">📢</span>
            <h3 class="fs-5 mb-2">Semua Kelas Berjalan Sesuai Jadwal</h3>
            <p class="small tac-muted mb-4">
                Saat ini belum ada pengumuman khusus atau libur. Seluruh kelas reguler berjalan seperti biasa.
            </p>
            <x-site.btn :href="route('public.contact')" size="sm" variant="coral">Tanya Info Pendaftaran</x-site.btn>
        </div>
    @endif
</x-site.section>

{{-- ─── 4. Kenapa Tarakan Art Class ────────────────────────────── --}}
<x-site.section tone="paper">
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

{{-- ─── 5. Preview galeri ──────────────────────────────────────── --}}
<x-site.section tone="paper-2">
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

{{-- ─── 6. Testimoni ───────────────────────────────────────────── --}}
<x-site.section tone="paper">
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

@push('scripts')
<script>
// Angka statistik hero dihitung naik dari 0 begitu masuk layar.
(function () {
    var nodes = document.querySelectorAll('[data-tac-count]');
    if (!nodes.length) return;

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || !('IntersectionObserver' in window)) return; // biarkan nilai akhir

    var DURATION = 1400;

    function run(el) {
        var target = parseInt(el.dataset.tacCount, 10);
        if (isNaN(target)) return;

        var start = null;
        el.textContent = '0';

        function step(now) {
            if (start === null) start = now;
            var p = Math.min((now - start) / DURATION, 1);
            // ease-out: cepat di awal, melambat menjelang angka akhir.
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(step);
        }

        requestAnimationFrame(step);
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            run(entry.target);
        });
    }, { threshold: 0.5 });

    nodes.forEach(function (el) { observer.observe(el); });
})();
</script>
@endpush
