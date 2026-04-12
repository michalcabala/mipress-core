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

    {{-- Right: live conversion previews (1/3 width) --}}
    <div class="space-y-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Náhledy konverzí</h3>
            <p class="text-xs text-gray-500">Živá simulace výřezů podle focal pointu.</p>
        </div>

        <div class="space-y-3">
            <template x-for="conversion in conversions" :key="conversion.name">
                <div class="rounded-xl border border-gray-200 bg-white p-3 space-y-2 shadow-xs dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white" x-text="conversion.label"></p>
                            <p class="text-xs text-gray-500" x-text="conversion.h ? `${conversion.w} × ${conversion.h}` : `šířka ${conversion.w} px`"></p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                            :class="conversion.mode === 'crop' ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                            x-text="conversion.mode"
                        ></span>
                    </div>

                    {{-- Always show original image with live focal‑point simulation --}}
                    <div
                        class="overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
                        :style="`aspect-ratio:${aspectRatio(conversion)};`"
                    >
                        <img
                            :src="imageUrl"
                            class="h-full w-full"
                            :class="conversion.mode === 'crop' ? 'object-cover' : 'object-contain'"
                            :style="conversion.mode === 'crop' ? `object-position:${x}% ${y}%;` : 'object-position:center;'"
                            alt=""
                        >
                    </div>

                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-medium"
                            :class="isGenerated(conversion) ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
                            x-text="isGenerated(conversion) ? '✓ vygenerováno' : '○ čeká na generování'"
                        ></span>
                        <button
                            type="button"
                            class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                            x-show="conversion.edit_action"
                            x-on:click="if (conversion.edit_action && $wire?.mountAction) $wire.mountAction(conversion.edit_action)"
                        >
                            Upravit ořez
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
