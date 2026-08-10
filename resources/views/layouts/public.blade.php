
@php
    $siteName = config('site.name');
    $pageTitle = trim($__env->yieldContent('title'));
    $metaTitle = $pageTitle ? $pageTitle.' — '.$siteName : $siteName.' — '.config('site.tagline');
    $metaDescription = trim($__env->yieldContent('description')) ?: config('site.description');
    $ogImage = is_file(public_path('images/og-image.jpg')) ? asset('images/og-image.jpg') : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Favicon dari logo resmi. Dipakai berkas PNG hasil ubahan images/ogo.jpg,
         bukan JPEG-nya langsung: dukungan JPEG sebagai favicon tidak merata
         antar peramban, dan penyusutan 225px → 16px oleh peramban hasilnya
         lebih kotor daripada penyusutan yang disiapkan lebih dulu.
         ?v= dinaikkan supaya favicon lama tidak nyangkut di singgahan. --}}
    <link rel="icon" href="{{ asset('images/logo-32.png') }}?v=3" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('images/logo-192.png') }}?v=3" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-180.png') }}?v=3">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="id_ID">
    @if($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">

    {{-- Bootstrap 5 (sama seperti sisi admin) --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Font sistem desain --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- Tema "kotak krayon" — override Bootstrap, khusus website publik --}}
    <link href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}" rel="stylesheet">

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
