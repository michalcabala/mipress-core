<x-filament-widgets::widget>
    <div class="flex flex-wrap items-start">
        @foreach ($items as $item)
            <div class="mb-2 me-3 shrink-0">
                <x-filament::button
                    tag="a"
                    :href="$item['url']"
                    :color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                    :outlined="! $item['isActive']"
                    :icon="$item['icon']"
                    :badge="$item['count']"
                    :badge-color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                    size="xs"
                    wire:navigate
                    :aria-current="$item['isActive'] ? 'page' : null"
                >
                    {{ $item['label'] }}
                </x-filament::button>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
