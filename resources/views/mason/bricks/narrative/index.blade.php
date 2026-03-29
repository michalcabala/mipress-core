<section class="mp-brick mp-brick--narrative mp-brick--{{ $tone }}">
    <div class="mp-brick__container {{ $width === 'narrow' ? 'mp-brick__container--narrow' : '' }}">
        @if(filled($eyebrow))
            <p class="mp-brick__eyebrow">{{ $eyebrow }}</p>
        @endif

        @if(filled($heading))
            <h2 class="mp-brick__heading">{{ $heading }}</h2>
        @endif

        @if(filled($content))
            <div class="mp-prose">
                {!! $content !!}
            </div>
        @endif
    </div>
</section>
