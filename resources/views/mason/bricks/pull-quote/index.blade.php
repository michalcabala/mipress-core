<section class="mp-brick mp-brick--quote {{ $alignment === 'start' ? 'mp-brick--quote-start' : '' }}">
    <div class="mp-brick__container mp-brick__container--narrow">
        <blockquote class="mp-quote">
            <p class="mp-quote__text">“{{ $quote }}”</p>

            @if(filled($author) || filled($role))
                <footer class="mp-quote__meta">
                    @if(filled($author))
                        <strong>{{ $author }}</strong>
                    @endif

                    @if(filled($role))
                        <span>{{ $role }}</span>
                    @endif
                </footer>
            @endif
        </blockquote>
    </div>
</section>
