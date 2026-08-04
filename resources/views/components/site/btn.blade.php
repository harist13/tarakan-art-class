@props([
    'href' => null,
    // primary (ink) | accent (sun) | coral | ghost (outline) | light
    'variant' => 'primary',
    'size' => 'md',        // sm | md | lg
])

@php
    $variants = [
        'primary' => '',
        'accent' => 'tac-btn-accent',
        'coral' => 'tac-btn-coral',
        'ghost' => 'tac-btn-ghost',
        'light' => 'tac-btn-light',
    ];

    $sizes = ['sm' => 'tac-btn-sm', 'md' => '', 'lg' => 'tac-btn-lg'];

    $classes = trim('tac-btn '.($variants[$variant] ?? '').' '.($sizes[$size] ?? ''));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes)->merge(['type' => 'button']) }}>{{ $slot }}</button>
@endif
