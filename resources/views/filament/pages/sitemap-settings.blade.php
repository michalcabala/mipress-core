<x-filament-panels::page>
    @php
        $lastGenerated = $this->getLastGeneratedInfo();
    @endphp

    @if($lastGenerated)
        <x-filament::section>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="fal-clock" class="h-4 w-4" />
                <span>{{ $lastGenerated }}</span>
            </div>
        </x-filament::section>
    @endif

    {{ $this->form }}
</x-filament-panels::page>
