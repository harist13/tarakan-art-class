
@php
    $siteName = config('site.name');
    $pageTitle = trim($__env->yieldContent('title'));
    $metaTitle = $pageTitle ? $pageTitle.' — '.$siteName : $siteName.' — '.config('site.tagline');
    $metaDescription = trim($__env->yieldContent('description')) ?: config('site.description');
    // Gambar pratinjau saat tautan dibagikan. og-image.jpg (1200x630) adalah
    // yang ideal; selama belum dibuat, logo dipakai supaya kartu pratinjau di
    // WhatsApp/Facebook tidak pernah kosong.
    $ogImage = is_file(public_path('images/og-image.jpg'))
        ? asset('images/og-image.jpg')
        : asset('images/logo-192.png');
    $ogImageLarge = is_file(public_path('images/og-image.jpg'));

    // Halaman bisa menolak diindeks lewat @section('robots', 'noindex, nofollow').
    $robots = trim($__env->yieldContent('robots')) ?: 'index, follow, max-image-preview:large, max-snippet:-1';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="robots" content="{{ $robots }}">
    <meta name="theme-color" content="#DD5843">

    {{-- Bukti kepemilikan situs untuk Google Search Console. Kosong = tidak dicetak. --}}
    @if(config('site.seo.google_verification'))
        <meta name="google-site-verification" content="{{ config('site.seo.google_verification') }}">
    @endif

    {{-- Favicon dari logo resmi. Dipakai berkas PNG hasil ubahan images/ogo.jpg,
         bukan JPEG-nya langsung: dukungan JPEG sebagai favicon tidak merata
         antar peramban, dan penyusutan 225px → 16px oleh peramban hasilnya
         lebih kotor daripada penyusutan yang disiapkan lebih dulu.
         ?v= dinaikkan supaya favicon lama tidak nyangkut di singgahan.

         Urutannya penting untuk Google Penelusuran: ikon di hasil pencarian
         diambil dari beranda dan harus persegi dengan sisi kelipatan 48px.
         Karena itu yang 96 & 192 didaftarkan lebih dulu — logo-32 dulu ada di
         urutan pertama dan tidak memenuhi syarat itu. /favicon.ico ikut
         didaftarkan (dan tersedia di akar domain) karena itu tempat cadangan
         yang dicari Google sendiri kalau deklarasi di bawah tidak terpakai. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=4" sizes="any">
    <link rel="icon" href="{{ asset('images/logo-96.png') }}?v=4" type="image/png" sizes="96x96">
    <link rel="icon" href="{{ asset('images/logo-192.png') }}?v=4" type="image/png" sizes="192x192">
    <link rel="icon" href="{{ asset('images/logo-32.png') }}?v=4" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-180.png') }}?v=4">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="{{ $ogImageLarge ? 'summary_large_image' : 'summary' }}">

    {{-- Bootstrap 5 (sama seperti sisi admin) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Font sistem desain --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tema "kotak krayon" — override Bootstrap, khusus website publik --}}
    <link href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}" rel="stylesheet">

    {{-- Data terstruktur. Tidak dicetak di halaman yang minta noindex —
         percuma menjelaskan halaman yang memang tidak boleh masuk indeks. --}}
    @unless(str_contains($robots, 'noindex'))
        @include('partials.site-schema', ['pageTitle' => $pageTitle, 'metaTitle' => $metaTitle, 'metaDescription' => $metaDescription])
    @endunless

    @stack('head')
</head>
<body>

    <a href="#konten" class="tac-skip-link">Lompat ke konten</a>

    @include('partials.site-navbar')

    <main id="konten">
        @yield('content')
    </main>

    @include('partials.site-footer')
    @include('partials.site-whatsapp')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
