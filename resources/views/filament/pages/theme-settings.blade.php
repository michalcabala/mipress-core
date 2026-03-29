<x-filament-panels::page>
    @php
        $activeTheme = $this->getActiveThemeManifest();
        $inactiveThemes = $this->getInactiveThemes();
        $hasThemes = $this->getThemes()->isNotEmpty();
    @endphp

    {{-- Active Theme --}}
    @if($activeTheme)
        <x-filament::section icon="fal-circle-check" icon-color="success">
            <x-slot name="heading">
                {{ $activeTheme->name }}
            </x-slot>

            <x-slot name="description">
                {{ $activeTheme->description ?? 'Aktuálně aktivní téma' }}
            </x-slot>

            <x-slot name="afterHeader">
                <x-filament::badge color="success">Aktivní</x-filament::badge>
            </x-slot>

            <div class="grid gap-6 sm:grid-cols-[minmax(0,20rem),minmax(0,1fr)]">
                @if($activeTheme->screenshot && file_exists($activeTheme->path . '/' . $activeTheme->screenshot))
                    <div class="aspect-video overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                        <img
                            src="{{ theme_file($activeTheme->screenshot, $activeTheme->slug) }}"
                            alt="{{ $activeTheme->name }}"
                            class="h-full w-full object-cover"
                        />
                    </div>
                @else
                    <div class="flex aspect-video items-center justify-center rounded-lg bg-gray-50 ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                        <x-filament::icon icon="fal-palette" class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                    </div>
                @endif

                <div class="flex flex-col justify-center">
                    <dl class="grid grid-cols-[auto,1fr] gap-x-4 gap-y-1.5 text-sm">
                        <dt class="fi-in-text text-gray-500 dark:text-gray-400">Verze</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $activeTheme->version }}</dd>

                        @if($activeTheme->author)
                            <dt class="fi-in-text text-gray-500 dark:text-gray-400">Autor</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $activeTheme->author }}</dd>
                        @endif

                        <dt class="fi-in-text text-gray-500 dark:text-gray-400">Slug</dt>
                        <dd class="font-mono text-xs text-gray-950 dark:text-white">{{ $activeTheme->slug }}</dd>
                    </dl>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- Available (Inactive) Themes --}}
    @if($inactiveThemes->isNotEmpty())
        <x-filament::section icon="fal-swatchbook">
            <x-slot name="heading">Dostupná témata</x-slot>
            <x-slot name="description">
                {{ $inactiveThemes->count() }} {{ trans_choice('{1} téma k dispozici|[2,4] témata k dispozici|[5,*] témat k dispozici', $inactiveThemes->count()) }}
            </x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($inactiveThemes as $theme)
                    <div class="fi-section fi-section-has-header overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
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

                        <div class="p-4">
                            <h4 class="fi-section-header-heading text-sm">{{ $theme->name }}</h4>

                            @if($theme->description)
                                <p class="fi-section-header-description mt-0.5 line-clamp-2">{{ $theme->description }}</p>
                            @endif

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <x-filament::badge color="gray">v{{ $theme->version }}</x-filament::badge>
                                @if($theme->author)
                                    <x-filament::badge color="gray" icon="fal-user">{{ $theme->author }}</x-filament::badge>
                                @endif
                            </div>

                            <div class="mt-4">
                                <x-filament::button
                                    wire:click="activateTheme('{{ $theme->slug }}')"
                                    wire:confirm="Opravdu chcete aktivovat téma &quot;{{ $theme->name }}&quot;?"
                                    color="gray"
                                    size="sm"
                                    class="w-full"
                                >
                                    Aktivovat téma
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
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
