<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">SEO kontrola</p>
            <h3 class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">Základní health warnings</h3>
        </div>

        @if ($warnings === [])
            <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                Bez zjevných problémů
            </span>
        @else
            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                {{ count($warnings) }} upozornění
            </span>
        @endif
    </div>

    @if ($warnings === [])
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
            Výchozí SEO vrstva má vyplněné klíčové údaje pro title, description, sdílení a structured data.
        </p>
    @else
        <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
            @foreach ($warnings as $warning)
                <li class="flex items-start gap-2">
                    <span class="mt-1 h-2 w-2 rounded-full bg-amber-500"></span>
                    <span>{{ $warning }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
