{{-- Curator focal-point picker — fullscreen modal with live aspect ratio previews --}}
@php
    $record = $getRecord();
    $isImage = $record && is_media_resizable($record->ext ?? '');
    $imageUrl = $isImage ? $record->url : null;
@endphp

@if ($isImage)
    <div
        x-data="{
            open: false,
            x: $wire.get('data.focal_point_x') ?? 50,
            y: $wire.get('data.focal_point_y') ?? 50,
            dragging: false,
            debounceTimer: null,
            imgRect: { left: 0, top: 0, width: 0, height: 0 },

            ratios: [
                { label: '1:1', w: 1, h: 1 },
                { label: '4:3', w: 4, h: 3 },
                { label: '3:2', w: 3, h: 2 },
                { label: '16:9', w: 16, h: 9 },
                { label: '9:16', w: 9, h: 16 },
                { label: '3:4', w: 3, h: 4 },
                { label: '21:9', w: 21, h: 9 },
                { label: 'Open Graph', w: 1200, h: 630 },
            ],

            normalizePoint(value) {
                const n = Number(value)
                return Number.isFinite(n) ? Math.round(Math.min(100, Math.max(0, n))) : 50
            },

            setPoint(nextX, nextY) {
                this.x = this.normalizePoint(nextX)
                this.y = this.normalizePoint(nextY)
                this.debouncedSyncWire()
            },

            debouncedSyncWire() {
                clearTimeout(this.debounceTimer)
                this.debounceTimer = setTimeout(() => this.syncWire(), 150)
            },

            syncWire() {
                $wire.set('data.focal_point_x', this.x)
                $wire.set('data.focal_point_y', this.y)
            },

            calcImgRect(img) {
                if (!img || !img.naturalWidth) return
                const cRect = img.parentElement.getBoundingClientRect()
                const cW = cRect.width, cH = cRect.height
                const iRatio = img.naturalWidth / img.naturalHeight
                const cRatio = cW / cH
                let rW, rH
                if (iRatio > cRatio) { rW = cW; rH = cW / iRatio }
                else { rH = cH; rW = cH * iRatio }
                this.imgRect = {
                    left: (cW - rW) / 2,
                    top: (cH - rH) / 2,
                    width: rW,
                    height: rH,
                }
            },

            updateFromPointer(event) {
                const img = event.currentTarget.querySelector('img')
                if (img) this.calcImgRect(img)
                const cRect = event.currentTarget.getBoundingClientRect()
                const px = event.clientX - cRect.left - this.imgRect.left
                const py = event.clientY - cRect.top - this.imgRect.top
                this.setPoint(
                    (px / this.imgRect.width) * 100,
                    (py / this.imgRect.height) * 100,
                )
            },

            crosshairStyle() {
                return `left:${this.imgRect.left + (this.x / 100) * this.imgRect.width}px;top:${this.imgRect.top + (this.y / 100) * this.imgRect.height}px;width:${this.imgRect.width}px;height:${this.imgRect.height}px`
            },

            reset() {
                this.setPoint(50, 50)
            },

            openModal() {
                this.open = true
                document.body.style.overflow = 'hidden'
                this.$nextTick(() => {
                    const img = this.$refs.pickerImg
                    if (img && img.complete) this.calcImgRect(img)
                })
            },

            closeModal() {
                this.open = false
                document.body.style.overflow = ''
            },

            init() {
                this.x = this.normalizePoint(this.x)
                this.y = this.normalizePoint(this.y)
            },
        }"
    >
        {{-- Inline compact preview + open button --}}
        <div class="flex items-center gap-4">
            <div class="relative h-20 max-w-32 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800">
                <img
                    src="{{ $imageUrl }}"
                    alt=""
                    class="h-full w-auto select-none object-contain"
                    draggable="false"
                >
                <div class="pointer-events-none absolute inset-0">
                    <div
                        class="absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500 shadow"
                        :style="`left:${x}%;top:${y}%`"
                    ></div>
                </div>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    Ohniskový bod: <span x-text="`${x}% / ${y}%`" class="text-primary-600 dark:text-primary-400"></span>
                </p>
                <button
                    type="button"
                    class="mt-1 inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                    x-on:click="openModal()"
                >
                    <x-filament::icon icon="far-crosshairs" class="size-4" />
                    Otevřít editor
                </button>
            </div>
        </div>

        {{-- Fullscreen modal --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:keydown.escape.window="if (open) closeModal()"
                class="fixed inset-0 z-50 flex flex-col bg-gray-950/80 backdrop-blur-sm"
                style="display: none;"
            >
                {{-- Modal content --}}
                <div class="flex h-full flex-col overflow-hidden bg-white dark:bg-gray-900">
                    {{-- Header --}}
                    <div class="flex shrink-0 items-center justify-between border-b border-gray-200 px-6 py-3 dark:border-white/10">
                        <div class="flex items-center gap-3">
                            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Editor ohniskového bodu</h2>
                            <span class="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-400" x-text="`${x}% / ${y}%`"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                                x-on:click="reset()"
                            >
                                Reset (50/50)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-700"
                                x-on:click="closeModal()"
                            >
                                Hotovo
                            </button>
                        </div>
                    </div>

                    {{-- Body --}}
                    <div class="flex min-h-0 flex-1 gap-0">
                        {{-- Left: FP picker --}}
                        <div class="flex w-1/2 flex-col gap-4 overflow-y-auto border-r border-gray-200 p-6 dark:border-white/10">
                            <div>
                                <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
                                    Klikněte nebo táhněte na obrázku pro nastavení bodu kompozice.
                                </p>
                            </div>

                            {{-- Interactive image --}}
                            <div
                                class="relative flex cursor-crosshair items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800"
                                style="max-height: calc(100vh - 16rem);"
                                x-on:pointerdown="dragging = true; updateFromPointer($event)"
                                x-on:pointermove="if (dragging) updateFromPointer($event)"
                                x-on:pointerup.window="dragging = false"
                                x-on:pointerleave="dragging = false"
                            >
                                <img
                                    x-ref="pickerImg"
                                    x-on:load="calcImgRect($event.target)"
                                    src="{{ $imageUrl }}"
                                    alt="{{ $record->alt ?? $record->name }}"
                                    class="max-h-full max-w-full select-none object-contain"
                                    draggable="false"
                                >

                                {{-- Crosshair overlay — positioned relative to actual image bounds --}}
                                <div class="pointer-events-none absolute inset-0" x-show="imgRect.width > 0">
                                    <div class="absolute h-px bg-white/90 shadow-sm" :style="`top:${imgRect.top + (y / 100) * imgRect.height}px;left:${imgRect.left}px;width:${imgRect.width}px`"></div>
                                    <div class="absolute w-px bg-white/90 shadow-sm" :style="`left:${imgRect.left + (x / 100) * imgRect.width}px;top:${imgRect.top}px;height:${imgRect.height}px`"></div>
                                    <div
                                        class="absolute size-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500 shadow-lg ring-4 ring-primary-500/25"
                                        :style="`left:${imgRect.left + (x / 100) * imgRect.width}px;top:${imgRect.top + (y / 100) * imgRect.height}px`"
                                    ></div>
                                </div>
                            </div>

                            {{-- X / Y inputs --}}
                            <div class="grid grid-cols-[auto_1fr_auto_1fr] items-center gap-3 text-sm">
                                <label class="text-gray-500 dark:text-gray-400">X</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    x-bind:value="x"
                                    x-on:input="setPoint($event.target.value, y)"
                                    class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-xs outline-hidden dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                >
                                <label class="text-gray-500 dark:text-gray-400">Y</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    x-bind:value="y"
                                    x-on:input="setPoint(x, $event.target.value)"
                                    class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-sm text-gray-900 shadow-xs outline-hidden dark:border-white/10 dark:bg-gray-900 dark:text-white"
                                >
                            </div>

                            <p class="rounded-xl bg-gray-50 px-3 py-2 text-xs leading-5 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                Ohniskový bod určuje, která část obrázku zůstane viditelná při ořezu do různých poměrů stran.
                            </p>
                        </div>

                        {{-- Right: Live aspect ratio previews --}}
                        <div class="flex w-1/2 flex-col overflow-y-auto p-6">
                            <h3 class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">Živé náhledy</h3>
                            <div class="grid grid-cols-2 gap-4 xl:grid-cols-3">
                                <template x-for="ratio in ratios" :key="ratio.label">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-gray-950 dark:text-white" x-text="ratio.label"></span>
                                            <span class="text-xs text-gray-400" x-text="`${ratio.w}:${ratio.h}`" x-show="ratio.label === 'Open Graph'"></span>
                                        </div>
                                        <div
                                            class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800"
                                            :style="`aspect-ratio: ${ratio.w} / ${ratio.h}`"
                                        >
                                            <img
                                                src="{{ $imageUrl }}"
                                                alt=""
                                                class="absolute inset-0 size-full select-none object-cover"
                                                :style="`object-position:${x}% ${y}%`"
                                                draggable="false"
                                            >
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
@else
    <div class="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
        Ohniskový bod je dostupný pouze pro obrázky.
    </div>
@endif
