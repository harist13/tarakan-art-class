@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
    'align' => 'center',   // center | left
    'level' => 'h2',
    'invert' => false,
])

<div class="{{ $align === 'center' ? 'mx-auto text-center' : '' }} tac-max-prose">
    @if($eyebrow)
        <p class="mb-0">
            <span class="tac-eyebrow {{ $invert ? 'tac-eyebrow-invert' : '' }}">{{ $eyebrow }}</span>
        </p>
    @endif

    <{{ $level }} class="fs-2 lh-sm mb-0 {{ $invert ? 'tac-text-paper' : 'tac-text-ink' }}">
        {!! $title !!}
    </{{ $level }}>

    @if($subtitle)
        <p class="mt-3 mb-0 lh-lg {{ $invert ? 'tac-muted-invert' : 'tac-muted' }}">
            {{ $subtitle }}
        </p>
    @endif

    {{ $slot }}
</div>
