<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
    <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Social preview</p>
    </div>

    @if (filled($seo['image_url'] ?? null))
        <div class="aspect-[1.91/1] overflow-hidden bg-slate-100 dark:bg-slate-900">
            <img src="{{ $seo['image_url'] }}" alt="{{ $seo['image_alt'] ?? ($seo['title'] ?? 'Náhled sdílení') }}" class="h-full w-full object-cover" />
        </div>
    @else
        <div class="flex aspect-[1.91/1] items-center justify-center bg-linear-to-br from-slate-100 to-slate-200 text-sm font-medium text-slate-500 dark:from-slate-900 dark:to-slate-800 dark:text-slate-400">
            Chybí výchozí OG obrázek
        </div>
    @endif

    <div class="space-y-2 px-4 py-4">
        <p class="text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
            {{ preg_replace('#^https?://#', '', $seo['canonical_url'] ?? 'www.example.cz') }}
        </p>
        <h3 class="text-base font-semibold leading-snug text-slate-900 dark:text-white">
            {{ str($seo['title'] ?? 'Bez titulku')->limit(88) }}
        </h3>
        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
            {{ str($seo['description'] ?: 'Výchozí description se použije i pro sociální sdílení, pokud položka nemá vlastní meta popis.')->limit(140) }}
        </p>
        <div class="flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">OG: {{ $seo['og_type'] ?? 'website' }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">X: {{ $seo['twitter_card'] ?? 'summary_large_image' }}</span>
            @if (filled($seo['site_name'] ?? null))
                <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $seo['site_name'] }}</span>
            @endif
        </div>
    </div>
</div>
