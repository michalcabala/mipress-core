<div class="fi-ta-record-state-links flex flex-wrap items-center gap-x-8 gap-y-3 text-sm md:gap-x-10">
    @foreach ($items as $item)
        <a
            href="{{ $item['url'] }}"
            @class([
                'group inline-flex items-center gap-2.5 rounded-full px-0.5 py-0.5 transition' => true,
                'text-gray-950 dark:text-white' => $item['active'],
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => ! $item['active'],
            ])
            @if ($item['active'])
                aria-current="page"
            @endif
        >
            <span class="inline-flex items-center gap-2">
                <x-filament::icon
                    :icon="$item['icon']"
                    @class([
                        'fi-size-sm transition',
                        $item['iconClass'],
                    ])
                />

                <span @class([
                    'font-semibold' => $item['active'],
                    'font-medium' => ! $item['active'],
                ])>
                    {{ $item['label'] }}
                </span>
            </span>

            <x-filament::badge :color="$item['color']" size="sm">
                {{ number_format($item['count'], 0, ',', ' ') }}
            </x-filament::badge>
        </a>
    @endforeach
</div>
