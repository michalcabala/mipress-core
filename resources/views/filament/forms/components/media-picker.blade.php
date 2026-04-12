@php
    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $isMultiple = $isMultiple();
    $isReorderable = $isReorderable();
    $selectedMedia = $getSelectedMediaData();
    $pickerRenderKey = 'media-picker-'.$statePath.'-'.md5((string) json_encode($selectedMedia));
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    <div
        wire:key="{{ $pickerRenderKey }}"
        x-data="{
            items: @js($selectedMedia),
            multiple: @js($isMultiple),
            reorderable: @js($isReorderable),
            state: $wire.entangle(@js($statePath)),
            draggedIndex: null,
            remove(id) {
                if (! this.multiple) {
                    this.state = null
                    this.items = []
                    return
                }

                this.state = (this.state ?? []).filter((item) => Number(item) !== Number(id))
                this.items = this.items.filter((item) => Number(item.id) !== Number(id))
            },
            drag(index) {
                if (! this.reorderable) {
                    return
                }

                this.draggedIndex = index
            },
            drop(index) {
                if (! this.reorderable || this.draggedIndex === null || this.draggedIndex === index) {
                    this.draggedIndex = null
                    return
                }

                const moved = this.items.splice(this.draggedIndex, 1)[0]
                this.items.splice(index, 0, moved)
                this.state = this.items.map((item) => item.id)
                this.draggedIndex = null
            },
        }"
        class="space-y-4"
    >
        <div>
            {{ $getAction('manageMedia') }}
        </div>

        <template x-if="! multiple && items.length">
            <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-xs">
                <template x-if="items[0]?.url">
                    <img :src="items[0].url" :alt="items[0].name" class="h-20 w-20 rounded-lg object-cover">
                </template>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-950" x-text="items[0]?.name"></p>
                </div>

                <button type="button" class="text-sm text-danger-600 hover:text-danger-700" x-on:click="remove(items[0].id)">
                    Odebrat
                </button>
            </div>
        </template>

        <template x-if="multiple && items.length">
            <div class="space-y-2">
                <template x-for="(item, index) in items" :key="item.id">
                    <div
                        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-xs"
                        :draggable="reorderable"
                        x-on:dragstart="drag(index)"
                        x-on:dragover.prevent
                        x-on:drop.prevent="drop(index)"
                    >
                        <span class="text-xs text-gray-500" x-show="reorderable">::</span>

                        <template x-if="item.url">
                            <img :src="item.url" :alt="item.name" class="h-16 w-16 rounded-lg object-cover">
                        </template>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-950" x-text="item.name"></p>
                        </div>

                        <button type="button" class="text-sm text-danger-600 hover:text-danger-700" x-on:click="remove(item.id)">
                            Odebrat
                        </button>
                    </div>
                </template>
            </div>
        </template>
    </div>
</x-dynamic-component>
