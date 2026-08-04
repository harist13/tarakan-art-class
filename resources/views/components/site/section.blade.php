@props([
    // paper | paper-2 | ink — latar selang-seling antar section
    'tone' => 'paper',
    'id' => null,
])

@php
    $tones = [
        'paper' => 'tac-bg-paper',
        'paper-2' => 'tac-bg-paper-2',
        'ink' => 'tac-bg-ink tac-text-paper',
    ];
@endphp

<section @if($id) id="{{ $id }}" @endif {{ $attributes->class(['tac-section', $tones[$tone] ?? $tones['paper']]) }}>
    <div class="container">
        {{ $slot }}
    </div>
</section>
