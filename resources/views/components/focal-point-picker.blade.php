@props([
    'media',
    'imageUrl' => null,
    'conversions' => [],
])

@php
    $imageWidth = (int) ($media?->getCustomProperty('width') ?? 0);
    $imageHeight = (int) ($media?->getCustomProperty('height') ?? 0);
@endphp

<div
    x-data="{
        imageUrl: @js($imageUrl),
        conversions: @js($conversions),
        imgW: @js($imageWidth),
        imgH: @js($imageHeight),
        x: $wire.entangle('data.focal_point_x').live,
        y: $wire.entangle('data.focal_point_y').live,
        dragging: false,
        init() {
            this.x = this.normalizePoint(this.x)
            this.y = this.normalizePoint(this.y)

            const img = this.$refs.sourceImage
            if (img && img.complete) this.syncDimensions(img)
        },
        clamp(v, min, max) { return Math.min(Math.max(v, min), max) },
        normalizePoint(v) {
            const n = Number(v)
            return Number.isFinite(n) ? Math.round(this.clamp(n, 0, 100)) : 50
        },
        syncDimensions(img) {
            if (! img) return
            const nw = img.naturalWidth ?? 0
            const nh = img.naturalHeight ?? 0
            if (nw > 0 && ! this.imgW) this.imgW = nw
            if (nh > 0 && ! this.imgH) this.imgH = nh
        },
        updateFromPointer(e) {
            const r = e.currentTarget.getBoundingClientRect()
            this.x = this.normalizePoint(((e.clientX - r.left) / r.width) * 100)
            this.y = this.normalizePoint(((e.clientY - r.top) / r.height) * 100)
        },
        aspectRatio(c) {
            if (c.h) return `${c.w} / ${c.h}`
            if (! this.imgW || ! this.imgH) return `${c.w} / ${c.w}`
            return `${c.w} / ${Math.max(1, Math.round((this.imgH / Math.max(this.imgW, 1)) * c.w))}`
        },
        usesCropMode(c) {
            return ['crop', 'crop_resize'].includes(String(c.mode ?? 'resize'))
        },
        cropStrategy(c) {
            const fallback = this.usesCropMode(c) ? 'focal_point' : 'none'
            const strategy = String(c.default_crop_strategy ?? fallback)

            return ['none', 'center', 'focal_point', 'manual'].includes(strategy) ? strategy : fallback
        },
        usesLiveFocalPreview(c) {
            return this.usesCropMode(c) && this.cropStrategy(c) === 'focal_point' && this.supportsFocalPoint(c)
        },
        previewSource(c) {
            if (this.isGenerated(c) && ! this.usesLiveFocalPreview(c) && typeof c.url === 'string' && c.url.length > 0) {
                return c.url
            }

            return this.imageUrl
        },
        previewObjectPosition(c) {
            return this.usesLiveFocalPreview(c) ? `${this.x}% ${this.y}%` : 'center'
        },
        pendingPreviewLabel(c) {
            if (this.cropStrategy(c) === 'manual') return 'Čeká na ruční crop'
            if (this.cropStrategy(c) === 'none') return 'Bez fallbacku'

            return ''
        },
        strategyLabel(c) {
            switch (this.cropStrategy(c)) {
                case 'center': return 'střed'
                case 'manual': return 'ruční'
                case 'none': return 'bez fallbacku'
                default: return 'focal point'
            }
        },
        strategyClasses(c) {
            switch (this.cropStrategy(c)) {
                case 'center': return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                case 'manual': return 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                case 'none': return 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                default: return 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300'
            }
        },
        showsPendingFallback(c) {
            return this.usesCropMode(c) && ['manual', 'none'].includes(this.cropStrategy(c)) && ! this.isGenerated(c)
        },
        supportsFocalPoint(c) {
            return Boolean(c.supports_focal_point)
        },
        supportsManualCrop(c) {
            return Boolean(c.supports_manual_crop)
        },
        manualCropRequired(c) {
            return Boolean(c.manual_crop_required)
        },
        isImportant(c) {
            return Boolean(c.important)
        },
        modeLabel(c) {
            if (String(c.mode ?? '') === 'crop_resize') return 'thumbnail'
            if (String(c.mode ?? '') === 'crop') return 'crop'
            return 'resize'
        },
        isGenerated(c) {
            return typeof c.url === 'string' && c.url.length > 0
        },
        reset() { this.x = 50; this.y = 50 },
    }"
    class="grid gap-6 lg:grid-cols-3"
>
    {{-- Left: focal point editor (2/3 width) --}}
    <div class="lg:col-span-2 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Focal point</h3>
                <p class="text-xs text-gray-500">Kliknutím nebo tažením nastavte fokus obrázku.</p>
            </div>
            <button type="button" class="text-sm font-medium text-primary-600 hover:text-primary-700" x-on:click="reset()">
                Resetovat (50 / 50)
            </button>
        </div>

        <div
            class="relative cursor-crosshair overflow-hidden rounded-2xl border border-gray-200 bg-gray-50"
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
                class="w-full select-none object-contain"
                draggable="false"
            >

            <div class="pointer-events-none absolute inset-0">
                <div class="absolute h-full w-px bg-white/80 shadow" :style="`left:${x}%`"></div>
                <div class="absolute h-px w-full bg-white/80 shadow" :style="`top:${y}%`"></div>
                <div class="absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary-500 shadow-lg ring-2 ring-primary-500/30" :style="`left:${x}%;top:${y}%`"></div>
            </div>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Souřadnice: <span class="font-semibold" x-text="`${x} / ${y}`"></span>
        </p>
    </div>

    {{-- Right: live conversion previews (1/3 width), tiled grid --}}
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Náhledy konverzí</h3>
            <p class="text-xs text-gray-500">Náhled respektuje fallback strategii a focal point jen tam, kde je skutečně aktivní.</p>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <template x-for="conversion in conversions" :key="conversion.name">
                <div
                    class="rounded-xl border bg-white p-2 space-y-1.5 shadow-xs dark:bg-gray-900"
                    :class="isImportant(conversion) ? 'border-primary-200 ring-1 ring-primary-100 dark:border-primary-700/60 dark:ring-primary-900/40' : 'border-gray-200 dark:border-gray-700'"
                >
                    <div class="flex items-center justify-between gap-1">
                        <p class="text-xs font-semibold text-gray-950 truncate dark:text-white" x-text="conversion.label"></p>
                        <div class="flex items-center gap-1">
                            <span
                                x-show="usesCropMode(conversion)"
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                :class="strategyClasses(conversion)"
                                x-text="strategyLabel(conversion)"
                            ></span>
                            <span
                                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                                :class="usesCropMode(conversion) ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                                x-text="modeLabel(conversion)"
                            ></span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1">
                        <span
                            x-show="supportsFocalPoint(conversion)"
                            class="inline-flex items-center rounded-full bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700 dark:bg-sky-900/30 dark:text-sky-300"
                        >FP</span>
                        <span
                            x-show="supportsManualCrop(conversion)"
                            class="inline-flex items-center rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                        >MC</span>
                        <span
                            x-show="manualCropRequired(conversion)"
                            class="inline-flex items-center rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300"
                        >Required</span>
                        <span
                            x-show="isImportant(conversion)"
                            class="inline-flex items-center rounded-full bg-violet-50 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/30 dark:text-violet-300"
                        >Důležitá</span>
                    </div>

                    <div
                        class="relative overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
                        :style="`aspect-ratio:${aspectRatio(conversion)};`"
                    >
                        <img
                            :src="previewSource(conversion)"
                            class="h-full w-full"
                            :class="usesCropMode(conversion) ? 'object-cover' : 'object-contain'"
                            :style="usesCropMode(conversion) ? `object-position:${previewObjectPosition(conversion)};` : 'object-position:center;'"
                            alt=""
                        >

                        <div
                            x-show="showsPendingFallback(conversion)"
                            class="absolute inset-0 flex items-end bg-linear-to-t from-black/55 via-black/15 to-transparent p-2"
                        >
                            <span
                                class="inline-flex items-center rounded-full bg-white/90 px-2 py-1 text-[10px] font-medium text-gray-900"
                                x-text="pendingPreviewLabel(conversion)"
                            ></span>
                        </div>
                    </div>

                    <p
                        class="text-[10px] leading-4 text-gray-500 dark:text-gray-400"
                        x-show="conversion.editor_help_text"
                        x-text="conversion.editor_help_text"
                    ></p>

                    <p
                        class="text-[10px] leading-4 text-rose-600 dark:text-rose-400"
                        x-show="manualCropRequired(conversion) && ! isGenerated(conversion)"
                    >
                        Tato konverze vyžaduje ruční crop.
                    </p>

                    <div class="flex items-center justify-between gap-1">
                        <span
                            class="text-[10px] font-medium truncate"
                            :class="isGenerated(conversion) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                            x-text="isGenerated(conversion) ? '✓ hotovo' : '○ čeká'"
                        ></span>
                        <button
                            type="button"
                            class="shrink-0 text-[10px] font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            x-show="conversion.edit_action"
                            x-on:click="if (conversion.edit_action && $wire?.mountAction) $wire.mountAction(conversion.edit_action)"
                        >
                            Ořez
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
