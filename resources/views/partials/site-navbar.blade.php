@php
    $menu = [
        ['label' => 'Beranda', 'route' => 'public.home'],
        ['label' => 'Tentang', 'route' => 'public.about'],
        ['label' => 'Program', 'route' => 'public.programs'],
        ['label' => 'Galeri', 'route' => 'public.gallery'],
        ['label' => 'Jadwal', 'route' => 'public.schedule'],
        ['label' => 'Kontak', 'route' => 'public.contact'],
    ];
@endphp

<nav class="navbar navbar-expand-lg sticky-top tac-navbar py-2" aria-label="Menu utama">
    <div class="container">

        <a class="navbar-brand d-flex align-items-center gap-2 py-2" href="{{ route('public.home') }}">
            <span class="tac-brand-mark" aria-hidden="true">🎨</span>
            <span class="tac-display fs-5 fw-bolder mb-0 tac-text-ink">
                Tarakan<span class="tac-text-coral">Art</span>Class
            </span>
        </a>

        <button class="navbar-toggler border-2 rounded-3" type="button"
                data-bs-toggle="collapse" data-bs-target="#navPublik"
                aria-controls="navPublik" aria-expanded="false" aria-label="Buka menu"
                style="border-color: var(--tac-ink);">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navPublik">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 mt-3 mt-lg-0">
                @foreach($menu as $item)
                    @php $isActive = request()->routeIs($item['route']); @endphp
                    <li class="nav-item">
                        <a class="nav-link tac-nav-link {{ $isActive ? 'active' : '' }}"
                           href="{{ route($item['route']) }}"
                           @if($isActive) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach

                <li class="nav-item mt-2 mt-lg-0 ms-lg-2">
                    <a class="tac-btn tac-btn-accent tac-btn-sm w-100" href="{{ route('public.contact') }}">
                        Daftar Sekarang
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
