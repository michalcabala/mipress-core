<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">SERP preview</p>

    <div class="mt-4 space-y-2">
        <p class="truncate text-sm text-emerald-700 dark:text-emerald-300">
            {{ preg_replace('#^https?://#', '', $seo['canonical_url'] ?? 'www.example.cz') }}
        </p>

        <h3 class="text-lg font-medium leading-snug text-blue-700 dark:text-blue-300">
            {{ str($seo['title'] ?? 'Bez titulku')->limit(60) }}
        </h3>

        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">
            {{ str($seo['description'] ?: 'Vyplňte výchozí description nebo používejte excerpty, aby nevznikaly prázdné výsledky ve vyhledávání.')->limit(160) }}
        </p>
    </div>

    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
        Google title obvykle zkrátí kolem 55–60 znaků a description kolem 150–160 znaků.
    </p>
</div>
