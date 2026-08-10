@props([
    // paper | paper-2 | ink — latar selang-seling antar section
    'tone' => 'paper',
    'id' => null,
    // Aktifkan jejak cat yang mengikuti kursor (dipakai di section hero).
    'paint' => false,
])

@php
    $tones = [
        'paper' => 'tac-bg-paper',
        'paper-2' => 'tac-bg-paper-2',
        'ink' => 'tac-bg-ink tac-text-paper',
    ];
@endphp

<section @if($id) id="{{ $id }}" @endif
         {{ $attributes->class(['tac-section', $tones[$tone] ?? $tones['paper'], 'tac-paint-area' => $paint]) }}
         @if($paint) data-tac-paint @endif>
    @if($paint)
        @include('partials.site-paint-trail')
    @endif
    <div class="container">
        {{ $slot }}
    </div>
</section>
