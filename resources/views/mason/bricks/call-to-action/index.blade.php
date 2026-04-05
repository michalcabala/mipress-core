<section class="mx-auto w-full max-w-6xl px-4 sm:px-6">
    <div class="rounded-3xl border border-blue-200 bg-linear-to-br from-blue-600 to-cyan-500 p-7 text-white shadow-xl shadow-blue-500/25 sm:p-10 dark:border-blue-700">
        <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-end">
            <div>
                @if(filled($eyebrow))
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-100">{{ $eyebrow }}</p>
                @endif

                @if(filled($title))
                    <h2 class="mt-3 text-3xl font-semibold leading-tight sm:text-4xl" style="font-family: 'Space Grotesk', sans-serif;">{{ $title }}</h2>
                @endif

                @if(filled($text))
                    <p class="mt-3 text-sm leading-7 text-blue-50/95">{{ $text }}</p>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                @if(filled($primary_label) && filled($primary_url))
                    <a href="{{ $primary_url }}" class="inline-flex items-center rounded-xl bg-white px-5 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">
                        {{ $primary_label }}
                    </a>
                @endif

                @if(filled($secondary_label) && filled($secondary_url))
                    <a href="{{ $secondary_url }}" class="inline-flex items-center rounded-xl border border-blue-100/60 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ $secondary_label }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
