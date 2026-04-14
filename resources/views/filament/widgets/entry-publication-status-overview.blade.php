<x-filament-widgets::widget>
    <div class="flex flex-wrap gap-2">
        @foreach ($items as $item)
            <x-filament::button
                tag="a"
                :href="$item['url']"
                :color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                :outlined="! $item['isActive']"
                :icon="$item['icon']"
                :badge="$item['count']"
                :badge-color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                size="sm"
                wire:navigate
                :aria-current="$item['isActive'] ? 'page' : null"
            >
                {{ $item['label'] }}
            </x-filament::button>
        @endforeach
    </div>
</x-filament-widgets::widget>
