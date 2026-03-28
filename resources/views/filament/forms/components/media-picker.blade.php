@php
    $isMultiple = $isMultiple();
    $statePath = $getStatePath();
    $selectedMedia = $getSelectedMedia();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            open: false,
            search: '',
            media: [],
            loading: false,
            selected: {{ json_encode(
                $isMultiple
                    ? (is_array($getState()) ? array_map('intval', $getState()) : [])
                    : (filled($getState()) ? [(int) $getState()] : [])
            ) }},
            isMultiple: {{ $isMultiple ? 'true' : 'false' }},

            async loadMedia() {
                this.loading = true;
                const response = await $wire.getMediaPickerOptions('{{ $getId() }}', this.search);
                this.media = response;
                this.loading = false;
            },

            toggle(id) {
                if (this.isMultiple) {
                    const idx = this.selected.indexOf(id);
                    if (idx > -1) {
                        this.selected.splice(idx, 1);
                    } else {
                        this.selected.push(id);
                    }
                } else {
                    this.selected = [id];
                }
            },

            isSelected(id) {
                return this.selected.includes(id);
            },

            confirm() {
                const value = this.isMultiple ? this.selected : (this.selected[0] ?? null);
                $wire.set('{{ $statePath }}', value);
                this.open = false;
            },

            clear() {
                this.selected = [];
                $wire.set('{{ $statePath }}', this.isMultiple ? [] : null);
            },
        }"
        x-init="
            $watch('open', v => { if (v) loadMedia(); });
            $watch('search', () => { clearTimeout(window._mpTimer); window._mpTimer = setTimeout(() => loadMedia(), 300); });
        "
    >
        {{-- Current selection preview --}}
        <div class="flex flex-wrap gap-2 mb-2">
            @forelse($selectedMedia as $item)
                <div class="flex items-center gap-2 rounded-lg border bg-white p-2 shadow-sm text-sm">
                    @if($item['isImage'] || $item['isSvg'])
                        <img src="{{ $item['thumbnail'] }}" class="w-10 h-10 object-cover rounded" alt="" />
                    @else
                        <x-filament::icon icon="far-file" class="w-10 h-10 text-gray-400" />
                    @endif
                    <span class="max-w-32 truncate">{{ $item['name'] }}</span>
                </div>
            @empty
                <span class="text-sm text-gray-400 italic">Žádné médium nevybráno</span>
            @endforelse
        </div>

        {{-- Buttons --}}
        <div class="flex gap-2">
            <x-filament::button
                color="gray"
                icon="far-photo-film"
                @click="open = true"
            >
                {{ $isMultiple ? 'Vybrat média' : 'Vybrat médium' }}
            </x-filament::button>

            @if(filled($getState()))
                <x-filament::button
                    color="danger"
                    icon="far-xmark"
                    outlined
                    @click="clear()"
                >
                    Odebrat
                </x-filament::button>
            @endif
        </div>

        {{-- Modal --}}
        <div
            x-show="open"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
            @click.self="open = false"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[80vh] flex flex-col overflow-hidden">

                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b">
                    <h2 class="text-lg font-semibold">{{ $isMultiple ? 'Vybrat média' : 'Vybrat médium' }}</h2>
                    <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                        <x-filament::icon icon="far-xmark" class="w-5 h-5" />
                    </button>
                </div>

                {{-- Search --}}
                <div class="px-6 py-3 border-b">
                    <input
                        type="text"
                        x-model="search"
                        placeholder="Hledat média..."
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>

                {{-- Media grid --}}
                <div class="flex-1 overflow-y-auto p-6">
                    <template x-if="loading">
                        <div class="flex items-center justify-center h-32 text-gray-400">
                            Načítám...
                        </div>
                    </template>

                    <template x-if="!loading && media.length === 0">
                        <div class="flex items-center justify-center h-32 text-gray-400 text-sm">
                            Žádná média
                        </div>
                    </template>

                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3" x-show="!loading && media.length > 0">
                        <template x-for="item in media" :key="item.id">
                            <div
                                @click="toggle(item.id)"
                                class="relative cursor-pointer rounded-lg overflow-hidden border-2 transition-all"
                                :class="isSelected(item.id) ? 'border-primary-500 ring-2 ring-primary-300' : 'border-transparent hover:border-gray-300'"
                            >
                                <template x-if="item.isImage || item.isSvg">
                                    <img
                                        :src="item.thumbnail || item.url"
                                        class="w-full h-20 object-cover"
                                        :alt="item.name"
                                    />
                                </template>
                                <template x-if="!item.isImage && !item.isSvg">
                                    <div class="w-full h-20 bg-gray-100 flex items-center justify-center">
                                        <span class="text-gray-400 text-xs text-center px-1" x-text="item.type"></span>
                                    </div>
                                </template>

                                {{-- Selected indicator --}}
                                <div
                                    x-show="isSelected(item.id)"
                                    class="absolute top-1 right-1 w-5 h-5 rounded-full bg-primary-500 flex items-center justify-center"
                                >
                                    <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>

                                <div class="p-1">
                                    <p class="text-xs text-gray-600 truncate" x-text="item.name"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50">
                    <span class="text-sm text-gray-500">
                        <span x-text="selected.length"></span> vybráno
                    </span>
                    <div class="flex gap-2">
                        <x-filament::button color="gray" @click="open = false">Zrušit</x-filament::button>
                        <x-filament::button @click="confirm()">Potvrdit výběr</x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
