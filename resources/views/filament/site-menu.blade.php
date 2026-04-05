<x-filament::dropdown placement="bottom-end">
    <x-slot name="trigger">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-500 transition hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200"
        >
            <x-filament::icon icon="fal-globe-pointer" class="h-5 w-5 shrink-0" />
            <span class="hidden sm:inline text-sm">{{ $siteName }}</span>
        </button>
    </x-slot>

    <x-filament::dropdown.header>
        {{ $siteName }}
    </x-filament::dropdown.header>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            icon="fal-arrow-up-right-from-square"
            href="{{ url('/') }}"
            tag="a"
            target="_blank"
        >
            Zobrazit web
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>
