{{--
    Ilustrasi alat lukis untuk hero beranda — inline SVG bergaya flat dengan
    garis tebal, senada sistem desain (Ink/Coral/Sun/Sky/Leaf/Grape).
    Urutan gambar: latar → tube cat → kuas → palet (menutupi ujung kuas) → kilau.
--}}
<svg class="tac-hero-art" viewBox="0 0 420 380" role="img" aria-labelledby="tacHeroArtTitle">
    <title id="tacHeroArtTitle">Ilustrasi alat lukis: palet cat warna-warni, tiga kuas, dan tube cat</title>

    <g stroke-linecap="round" stroke-linejoin="round">

        {{-- Latar mesh lembut --}}
        <circle cx="150" cy="142" r="112" fill="rgba(255, 107, 94, 0.13)"/>
        <circle cx="278" cy="176" r="118" fill="rgba(63, 167, 224, 0.13)"/>
        <circle cx="205" cy="268" r="104" fill="rgba(253, 187, 45, 0.15)"/>

        {{-- Tube cat --}}
        <g class="tac-float-slow">
            <g transform="translate(72 296) rotate(-18)">
                <rect x="-10" y="-72" width="20" height="16" rx="4" fill="#241B36"/>
                <rect x="-7" y="-59" width="14" height="13" rx="2" fill="#E3E7F0" stroke="#241B36" stroke-width="2.5"/>
                <rect x="-18" y="-48" width="36" height="66" rx="9" fill="#FDBB2D" stroke="#241B36" stroke-width="2.5"/>
                <rect x="-20" y="15" width="40" height="14" rx="3" fill="#E5A420" stroke="#241B36" stroke-width="2.5"/>
                <path d="M-7 -30h14" stroke="#241B36" stroke-width="2.5" opacity="0.3"/>
                <path d="M-7 -20h14" stroke="#241B36" stroke-width="2.5" opacity="0.3"/>
            </g>
        </g>

        {{-- Kuas — ujungnya sengaja tersembunyi di balik palet --}}
        @foreach([
            ['x' => 152, 'y' => 244, 'rot' => -30, 'handle' => '#FF6B5E', 'bristle' => '#E04B3E'],
            ['x' => 205, 'y' => 250, 'rot' => -6,  'handle' => '#3FA7E0', 'bristle' => '#2A82B4'],
            ['x' => 256, 'y' => 246, 'rot' => 16,  'handle' => '#5BBE7A', 'bristle' => '#429A5E'],
        ] as $brush)
            <g transform="translate({{ $brush['x'] }} {{ $brush['y'] }}) rotate({{ $brush['rot'] }})">
                {{-- gagang --}}
                <rect x="-7" y="-124" width="14" height="72" rx="7"
                      fill="{{ $brush['handle'] }}" stroke="#241B36" stroke-width="2.5"/>
                {{-- cincin logam --}}
                <rect x="-8.5" y="-56" width="17" height="20" rx="4"
                      fill="#E3E7F0" stroke="#241B36" stroke-width="2.5"/>
                <path d="M-8.5 -46h17" stroke="#241B36" stroke-width="2" opacity="0.35"/>
                {{-- bulu kuas --}}
                <path d="M-8.5 -38C-9.5 -18 -5 -6 0 0C5 -6 9.5 -18 8.5 -38Z"
                      fill="{{ $brush['bristle'] }}" stroke="#241B36" stroke-width="2.5"/>
            </g>
        @endforeach

        {{-- Palet cat --}}
        <g transform="rotate(-6 210 268)">
            <ellipse cx="210" cy="268" rx="118" ry="68" fill="#FFFFFF" stroke="#241B36" stroke-width="2.5"/>
            {{-- lubang ibu jari --}}
            <ellipse cx="256" cy="288" rx="21" ry="15" fill="#FDFAF3" stroke="#241B36" stroke-width="2.5"/>
            {{-- tumpahan cat --}}
            @foreach([
                ['cx' => 140, 'cy' => 248, 'fill' => '#FF6B5E'],
                ['cx' => 178, 'cy' => 238, 'fill' => '#FDBB2D'],
                ['cx' => 216, 'cy' => 236, 'fill' => '#3FA7E0'],
                ['cx' => 254, 'cy' => 242, 'fill' => '#5BBE7A'],
                ['cx' => 290, 'cy' => 256, 'fill' => '#8B6FD1'],
            ] as $blob)
                <circle cx="{{ $blob['cx'] }}" cy="{{ $blob['cy'] }}" r="13"
                        fill="{{ $blob['fill'] }}" stroke="#241B36" stroke-width="2"/>
            @endforeach
            {{-- kilau pada permukaan palet --}}
            <path d="M118 292c14 14 40 22 66 22" stroke="#241B36" stroke-width="2.5" fill="none" opacity="0.2"/>
        </g>

        {{-- Kilau dekoratif --}}
        @foreach([
            ['x' => 98,  'y' => 88,  's' => 1.5, 'fill' => '#FDBB2D', 'float' => 'tac-float'],
            ['x' => 338, 'y' => 108, 's' => 1.9, 'fill' => '#FF6B5E', 'float' => 'tac-float-slow'],
            ['x' => 366, 'y' => 242, 's' => 1.1, 'fill' => '#3FA7E0', 'float' => 'tac-float'],
            ['x' => 48,  'y' => 168, 's' => 1.3, 'fill' => '#8B6FD1', 'float' => 'tac-float-slow'],
        ] as $spark)
            <g class="{{ $spark['float'] }}">
                <path d="M0 -10Q2 -2 10 0Q2 2 0 10Q-2 2 -10 0Q-2 -2 0 -10Z"
                      transform="translate({{ $spark['x'] }} {{ $spark['y'] }}) scale({{ $spark['s'] }})"
                      fill="{{ $spark['fill'] }}"/>
            </g>
        @endforeach
    </g>
</svg>
