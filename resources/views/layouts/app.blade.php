<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Tarakan Art Class</title>

    {{-- Sisi sistem tidak pernah boleh muncul di hasil pencarian. Larangan di
         robots.txt saja tidak cukup: halaman yang tertaut dari situs lain masih
         bisa terindeks tanpa pernah dirayapi. Meta ini yang benar-benar menutup. --}}
    <meta name="robots" content="noindex, nofollow">

    {{-- Favicon: palet & kuas lukis --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">

    @include('partials.styles')
    @stack('styles')
</head>
<body>

    <div class="d-flex">
        {{-- Sidebar --}}
        @include('partials.sidebar')

        {{-- Page Content --}}
        <div id="content">
            {{-- Topbar --}}
            @include('partials.topbar')

            {{-- Main Content Container --}}
            <div class="container-fluid px-4 main-container">
                @include('partials.alerts')
                @yield('content')
            </div>

            {{-- Footer --}}
            @include('partials.footer')
        </div>
    </div>

    @include('partials.scripts')
    @stack('scripts')
</body>
</html>
