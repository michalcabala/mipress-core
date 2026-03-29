<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($this->getThemes() as $theme)
            @php $isActive = $theme->slug === $this->getActiveTheme(); @endphp

            <div @class([
                'rounded-xl shadow-sm ring-1 overflow-hidden bg-white dark:bg-gray-900',
                'ring-gray-200 dark:ring-white/10' => ! $isActive,
                'ring-2 ring-primary-500 dark:ring-primary-400' => $isActive,
            ])>
                {{-- Screenshot --}}
                @if($theme->screenshot && file_exists($theme->path . '/' . $theme->screenshot))
                    <div class="aspect-video overflow-hidden bg-gray-100 dark:bg-gray-800">
                        <img
                            src="{{ asset('themes/' . $theme->slug . '/' . $theme->screenshot) }}"
                            alt="{{ $theme->name }}"
                            class="h-full w-full object-cover"
                        />
                    </div>
                @else
                    <div class="aspect-video bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                        <x-filament::icon
                            icon="fal-palette"
                            class="h-12 w-12 text-gray-300 dark:text-gray-600"
                        />
                    </div>
                @endif

                <div class="p-5 flex flex-col gap-4">
                    <div>
                        <div class="flex items-center justify-between gap-2 flex-wrap">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $theme->name }}
                            </h3>
                            @if($isActive)
                                <x-filament::badge color="success">Aktivní</x-filament::badge>
                            @endif
                        </div>

                        <dl class="mt-1 space-y-0.5 text-sm text-gray-500 dark:text-gray-400">
                            <div>
                                <dt class="inline font-medium text-gray-700 dark:text-gray-300">Verze:</dt>
                                <dd class="inline">{{ $theme->version }}</dd>
                            </div>
                            @if($theme->author)
                                <div>
                                    <dt class="inline font-medium text-gray-700 dark:text-gray-300">Autor:</dt>
                                    <dd class="inline">{{ $theme->author }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="inline font-medium text-gray-700 dark:text-gray-300">Slug:</dt>
                                <dd class="inline font-mono text-xs">{{ $theme->slug }}</dd>
                            </div>
                        </dl>
                    </div>

                    @unless($isActive)
                        <x-filament::button
                            wire:click="activateTheme('{{ $theme->slug }}')"
                            wire:confirm="Opravdu chcete aktivovat téma &quot;{{ $theme->name }}&quot;?"
                            color="primary"
                            size="sm"
                        >
                            Aktivovat
                        </x-filament::button>
                    @endunless
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-200 dark:ring-white/10 p-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Žádná témata nebyla nalezena. Vytvořte složku
                    <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">resources/themes/{slug}/</code>
                    s platným souborem
                    <code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">theme.json</code>.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
