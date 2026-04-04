<div class="flex items-center gap-3 px-3">
    @if($siteName)
        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $siteName }}</span>
    @endif

    <x-filament::button
        icon="fal-arrow-up-right-from-square"
        icon-position="after"
        href="{{ url('/') }}"
        target="_blank"
        rel="noopener noreferrer"
        tag="a"
        size="sm"
    >
        Zobrazit web
    </x-filament::button>
</div>
