<section class="mx-auto w-full max-w-6xl px-4 sm:px-6">
    <div class="space-y-6">
        @if(filled($heading))
            <div class="max-w-3xl">
                <h2 class="text-3xl font-semibold text-slate-900 dark:text-white" style="font-family: 'Space Grotesk', sans-serif;">{{ $heading }}</h2>

                @if(filled($intro))
                    <p class="mt-3 text-base leading-8 text-slate-600 dark:text-slate-300">{{ $intro }}</p>
                @endif
            </div>
        @endif

        <div class="grid gap-4" style="grid-template-columns: repeat({{ max(1, min(4, (int) $columns)) }}, minmax(0, 1fr));">
            @foreach($items as $item)
                <article class="rounded-2xl border border-slate-200 bg-white/90 p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900/85">
                    @if(filled($item['label'] ?? null))
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600 dark:text-blue-300">{{ $item['label'] }}</p>
                    @endif

                    <h3 class="mt-2 text-xl font-semibold text-slate-900 dark:text-white" style="font-family: 'Space Grotesk', sans-serif;">{{ $item['title'] ?? '' }}</h3>

                    @if(filled($item['text'] ?? null))
                        <p class="mt-2 text-sm leading-7 text-slate-600 dark:text-slate-300">{{ $item['text'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
