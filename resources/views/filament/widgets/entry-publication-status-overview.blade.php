<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200/80 bg-white/80 p-1.5 shadow-sm shadow-gray-200/50 dark:border-white/10 dark:bg-white/[0.03] dark:shadow-none">
        <div class="-mx-0.5 overflow-x-auto px-0.5 pb-0.5 [scrollbar-color:theme(colors.gray.300)_transparent] dark:[scrollbar-color:rgba(255,255,255,0.14)_transparent]">
            <div class="flex min-w-max items-center gap-2 text-gray-950 dark:text-white">
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
    </div>
</x-filament-widgets::widget>
