<x-filament-widgets::widget>
    <div class="-mx-1 overflow-x-auto px-1 pb-1">
        <div class="flex min-w-max items-center gap-2">
            @foreach ($items as $item)
                <a
                    href="{{ $item['url'] }}"
                    wire:navigate
                    @if ($item['isActive']) aria-current="page" @endif
                    class="{{ $item['itemClass'] }}"
                >
                    <x-filament::icon
                        :icon="$item['icon']"
                        class="h-4 w-4 shrink-0"
                    />

                    <span>{{ $item['label'] }}</span>

                    <span class="{{ $item['countClass'] }}">
                        {{ $item['count'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
