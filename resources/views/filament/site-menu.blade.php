<x-filament::dropdown placement="bottom-end">
    <x-slot name="trigger">
        <button
            type="button"
            class="fi-icon-btn inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-filament::icon icon="heroicon-o-globe-alt" class="h-5 w-5" />
            <span class="hidden sm:inline text-sm">{{ config('app.name') }}</span>
        </button>
    </x-slot>

    <x-filament::dropdown.list>
        <x-filament::dropdown.list.item
            icon="heroicon-o-arrow-top-right-on-square"
            href="{{ url('/') }}"
            tag="a"
            target="_blank"
        >
            Zobrazit web
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>