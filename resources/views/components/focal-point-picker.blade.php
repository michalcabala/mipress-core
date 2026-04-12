{{-- Unified focal-point picker (inline) — single source of Alpine logic --}}
@props([
    'media' => null,
    'imageUrl' => null,
    'conversions' => [],
    'xStatePath' => null,
    'yStatePath' => null,
    'xValue' => 50,
    'yValue' => 50,
])

@php
    $isImage = $media && method_exists($media, 'isImage') && $media->isImage();
    $imageWidth = (int) ($media?->getCustomProperty('width') ?? 0);
    $imageHeight = (int) ($media?->getCustomProperty('height') ?? 0);
@endphp

@if ($isImage)
    <div
        x-data="{
            imageUrl: @js($imageUrl),
            conversions: @js($conversions),
            imgW: @js($imageWidth),
            imgH: @js($imageHeight),
            xStatePath: @js($xStatePath),
            yStatePath: @js($yStatePath),
            x: @js((int) $xValue),
            y: @js((int) $yValue),
            dragging: false,
            debounceTimer: null,

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
                if (! this.xStatePath || ! this.yStatePath) return
                $wire.set(this.xStatePath, this.x)
                $wire.set(this.yStatePath, this.y)
            },

            updateFromPointer(event) {
                const rect = event.currentTarget.getBoundingClientRect()
                this.setPoint(
                    ((event.clientX - rect.left) / rect.width) * 100,
                    ((event.clientY - rect.top) / rect.height) * 100,
                )
            },

            syncDimensions(image) {
                if (! image) return
                if (image.naturalWidth > 0 && ! this.imgW) this.imgW = image.naturalWidth
                if (image.naturalHeight > 0 && ! this.imgH) this.imgH = image.naturalHeight
            },

            usesCropMode(c) {
                return ['crop', 'crop_resize'].includes(String(c.mode ?? 'resize'))
            },

            cropStrategy(c) {
                const fallback = this.usesCropMode(c) ? 'focal_point' : 'none'
                const s = String(c.default_crop_strategy ?? fallback)
                return ['none', 'center', 'focal_point', 'manual'].includes(s) ? s : fallback
            },

            usesLiveFocalPreview(c) {
                return this.usesCropMode(c) && this.cropStrategy(c) === 'focal_point' && Boolean(c.supports_focal_point)
            },

            previewSource(c) {
                if (this.isGenerated(c) && ! this.usesLiveFocalPreview(c) && typeof c.url === 'string' && c.url.length > 0) {
                    return c.url + (c.url.includes('?') ? '&' : '?') + 'v=' + (c.version ?? 0)
                }
                return this.imageUrl
            },

            previewObjectPosition(c) {
                return this.usesLiveFocalPreview(c) ? `${this.x}% ${this.y}%` : 'center'
            },

            pendingPreviewLabel(c) {
                if (this.cropStrategy(c) === 'manual' && ! this.isGenerated(c)) return 'Čeká na ruční crop'
                if (this.cropStrategy(c) === 'none' && ! this.isGenerated(c)) return 'Bez fallbacku'
                return ''
            },

            strategyLabel(c) {
                return { center: 'střed', manual: 'ruční', none: 'bez fallbacku', focal_point: 'focal point' }[this.cropStrategy(c)] ?? 'focal point'
            },

            isGenerated(c) {
                return typeof c.url === 'string' && c.url.length > 0
            },

            aspectRatio(c) {
                if (c.h) return `${c.w} / ${c.h}`
                if (this.imgW && this.imgH) return `${c.w} / ${Math.max(1, Math.round((this.imgH / Math.max(this.imgW, 1)) * c.w))}`
                return '1 / 1'
            },

            reset() {
                this.setPoint(50, 50)
            },

            init() {
                this.x = this.normalizePoint(this.x)
                this.y = this.normalizePoint(this.y)
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-col gap-6 lg:flex-row">
            {{-- Left: FP picker --}}
            <div class="w-full shrink-0 space-y-4 lg:w-72">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Focal Point</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Klikněte nebo táhněte na obrázku pro nastavení bodu kompozice.</p>
                </div>

                <div
                    class="relative cursor-crosshair overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800"
                    x-on:pointerdown="dragging = true; updateFromPointer($event)"
                    x-on:pointermove="if (dragging) updateFromPointer($event)"
                    x-on:pointerup.window="dragging = false"
                    x-on:pointerleave="dragging = false"
                >
                    <img
                        x-ref="sourceImage"
                        x-on:load="syncDimensions($event.target)"
                        src="{{ $imageUrl }}"
                        alt="{{ $media?->file_name }}"
                        class="aspect-4/3 w-full select-none object-cover"
                        :style="`object-position:${x}% ${y}%`"
                        draggable="false"
                    >

                    <div class="pointer-events-none absolute inset-0">
                        <div class="absolute h-full w-px bg-white/90 shadow-sm" :style="`left:${x}%`"></div>
                        <div class="absolute h-px w-full bg-white/90 shadow-sm" :style="`top:${y}%`"></div>
                        <div
                            class="absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500 shadow-lg ring-4 ring-primary-500/25"
                            :style="`left:${x}%;top:${y}%`"
                        ></div>
                    </div>
                </div>

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

                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400" x-text="`FP ${x}% / ${y}%`"></span>
                    <button
                        type="button"
                        class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                        x-on:click="reset()"
                    >
                        Reset (50/50)
                    </button>
                </div>

                <p class="rounded-xl bg-gray-50 px-3 py-2 text-xs leading-5 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    Focal point ovlivňuje crop konverze se strategií <span class="font-semibold">focal point</span>.
                    Změny se uloží se zbytkem formuláře.
                </p>
            </div>

            {{-- Right: Conversion grid --}}
            <div class="min-w-0 flex-1">
                <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Konverze</h3>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    <template x-for="c in conversions" :key="c.name">
                        <div class="space-y-2 rounded-xl border border-gray-200 bg-white p-3 shadow-xs dark:border-white/10 dark:bg-gray-900">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" x-text="c.label"></p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400" x-text="`${c.w} × ${c.h ?? 'auto'} px`"></p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="usesCropMode(c) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                                    x-text="usesCropMode(c) ? (String(c.mode) === 'crop_resize' ? 'thumbnail' : 'crop') : 'resize'"
                                ></span>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    x-show="usesCropMode(c)"
                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="{
                                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': cropStrategy(c) === 'center',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': cropStrategy(c) === 'manual',
                                        'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300': cropStrategy(c) === 'none',
                                        'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300': cropStrategy(c) === 'focal_point',
                                    }"
                                    x-text="strategyLabel(c)"
                                ></span>
                                <span
                                    x-show="c.has_manual_override"
                                    class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
                                >Override</span>
                            </div>

                            <div
                                class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
                                :style="`aspect-ratio:${aspectRatio(c)};`"
                            >
                                <img
                                    :src="previewSource(c)"
                                    class="h-full w-full"
                                    :class="usesCropMode(c) ? 'object-cover' : 'object-contain'"
                                    :style="usesCropMode(c) ? `object-position:${previewObjectPosition(c)};` : 'object-position:center;'"
                                    alt=""
                                >
                                <div
                                    x-show="usesCropMode(c) && ['manual', 'none'].includes(cropStrategy(c)) && ! isGenerated(c)"
                                    class="absolute inset-0 flex items-end bg-linear-to-t from-black/55 via-black/15 to-transparent p-2"
                                >
                                    <span class="rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium text-gray-900" x-text="pendingPreviewLabel(c)"></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="truncate text-[10px] font-medium"
                                    :class="isGenerated(c) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                                    x-text="isGenerated(c) ? '✓ hotovo' : '○ čeká'"
                                ></span>
                                <button
                                    type="button"
                                    class="text-[10px] font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                    x-show="c.edit_action"
                                    x-on:click="$wire.mountAction(c.edit_action)"
                                >
                                    Ořez
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center dark:border-white/10 dark:bg-gray-900">
        <p class="text-sm text-gray-500 dark:text-gray-400">Focal point je k dispozici pouze pro obrázky.</p>
    </div>
@endif