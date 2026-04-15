<x-filament-widgets::widget>
    <div class="flex flex-wrap gap-2">
        @foreach ($items as $item)
            <x-filament::link
                :href="$item['url']"
                :color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                :icon="$item['icon']"
                :badge="$item['count']"
                :badge-color="$item['isActive'] && ($item['key'] === 'all') ? 'primary' : $item['color']"
                size="sm"
                :weight="$item['isActive'] ? 'semibold' : 'medium'"
                :class="[
                    'underline decoration-2 underline-offset-4' => $item['isActive'],
                ]"
                wire:navigate
                :aria-current="$item['isActive'] ? 'page' : null"
            >
                {{ $item['label'] }}
            </x-filament::link>
        @endforeach
    </div>
</x-filament-widgets::widget>
