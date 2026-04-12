<section class="mp-brick mp-brick--accent">
    <div class="mp-brick__container">
        <div class="mp-cta">
            <div>
                @if (filled($eyebrow))
                    <p class="mp-brick__eyebrow">{{ $eyebrow }}</p>
                @endif

                @if (filled($title))
                    <h2 class="mp-brick__heading">{{ $title }}</h2>
                @endif

                @if (filled($text))
                    <p class="mp-cta__text">{{ $text }}</p>
                @endif
            </div>

            <div class="mp-cta__actions">
                @if (filled($primary_label) && filled($primary_url))
                    <a href="{{ $primary_url }}" class="mp-button mp-button--primary">
                        {{ $primary_label }}
                    </a>
                @endif

                @if (filled($secondary_label) && filled($secondary_url))
                    <a href="{{ $secondary_url }}" class="mp-button mp-button--ghost">
                        {{ $secondary_label }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
