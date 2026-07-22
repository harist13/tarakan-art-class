<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Tarakan Art Class</title>

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
