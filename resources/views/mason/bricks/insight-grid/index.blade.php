<section class="mp-brick mp-brick--default">
    <div class="mp-brick__container">
        @if (filled($heading))
            <div class="mp-section-heading">
                <h2 class="mp-brick__heading">{{ $heading }}</h2>

                @if (filled($intro))
                    <p class="mp-section-heading__intro">{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="mp-insight-grid {{ 'mp-insight-grid--'.max(1, min(4, (int) $columns)) }}">
            @foreach ($items as $item)
                <article class="mp-insight-card">
                    @if (filled($item['label'] ?? null))
                        <p class="mp-insight-card__label">{{ $item['label'] }}</p>
                    @endif

                    <h3>{{ $item['title'] ?? '' }}</h3>

                    @if (filled($item['text'] ?? null))
                        <p class="mp-insight-card__text">{{ $item['text'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
