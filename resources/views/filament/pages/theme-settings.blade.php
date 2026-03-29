<x-filament-panels::page>
    @php
        $themes = $this->getThemes();
        $activeTheme = $this->getActiveTheme();
        $hasThemes = $this->getThemes()->isNotEmpty();
    @endphp

    @if($hasThemes)
        <x-filament::section icon="fal-swatchbook">
            <x-slot name="heading">Témata</x-slot>
            <x-slot name="description">
                {{ $themes->count() }} {{ trans_choice('{1} téma|[2,4] témata|[5,*] témat', $themes->count()) }}
            </x-slot>

            <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));">
                @foreach($themes as $theme)
                    @php
                        $isActive = $theme->slug === $activeTheme;
                    @endphp

                    <article class="w-full overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        @if($theme->screenshot && file_exists($theme->path . '/' . $theme->screenshot))
                            <div class="aspect-video overflow-hidden bg-gray-50 dark:bg-gray-800">
                                <img
                                    src="{{ theme_file($theme->screenshot, $theme->slug) }}"
                                    alt="{{ $theme->name }}"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        @else
                            <div class="flex aspect-video items-center justify-center bg-gray-50 dark:bg-gray-800">
                                <x-filament::icon icon="fal-palette" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                            </div>
                        @endif

                        <div class="space-y-3 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <h4 class="fi-section-header-heading text-sm">{{ $theme->name }}</h4>
                                @if($isActive)
                                    <x-filament::badge color="success" icon="fal-check">Aktivní</x-filament::badge>
                                @endif
                            </div>

                            @if($theme->description)
                                <p class="fi-section-header-description line-clamp-2">{{ $theme->description }}</p>
                            @endif

                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge color="gray">v{{ $theme->version }}</x-filament::badge>
                                @if($theme->author)
                                    <x-filament::badge color="gray" icon="fal-user">{{ $theme->author }}</x-filament::badge>
                                @endif
                                <x-filament::badge color="gray" icon="fal-hashtag">{{ $theme->slug }}</x-filament::badge>
                            </div>

                            <div>
                                @if($isActive)
                                    <x-filament::button color="success" size="sm" class="w-full" disabled>
                                        Aktivní téma
                                    </x-filament::button>
                                @else
                                    <x-filament::button
                                        wire:click="activateTheme('{{ $theme->slug }}')"
                                        wire:confirm="Opravdu chcete aktivovat téma &quot;{{ $theme->name }}&quot;?"
                                        color="gray"
                                        size="sm"
                                        class="w-full"
                                    >
                                        Aktivovat téma
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Empty State --}}
    @unless($hasThemes)
        <x-filament::empty-state
            icon="fal-palette"
            heading="Žádná témata"
            description="Vytvořte složku resources/themes/{slug}/ s platným souborem theme.json."
        />
    @endunless
</x-filament-panels::page>
