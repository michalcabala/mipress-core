@php
    $toneClass = match ($tone) {
        'muted' => 'mp-brick--muted',
        'accent' => 'mp-brick--accent',
        default => 'mp-brick--default',
    };
    $widthClass = $width === 'narrow' ? 'mp-brick__container--narrow' : '';
@endphp

<section class="mp-brick {{ $toneClass }}">
    <div class="mp-brick__container {{ $widthClass }}">
        @if (filled($eyebrow))
            <p class="mp-brick__eyebrow">{{ $eyebrow }}</p>
        @endif

        @if (filled($heading))
            <h2 class="mp-brick__heading">{{ $heading }}</h2>
        @endif

        @if (filled($content))
            <div class="mp-prose">
                {!! $content !!}
            </div>
        @endif
    </div>
</section>
