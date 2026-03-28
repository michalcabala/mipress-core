<div
    x-data="{
        focalX: {{ $this->getRecord()->focal_point['x'] ?? 50 }},
        focalY: {{ $this->getRecord()->focal_point['y'] ?? 50 }},
        isDragging: false,
        getPosition(event, el) {
            const rect = el.getBoundingClientRect();
            const clientX = event.clientX ?? (event.touches?.[0]?.clientX ?? 0);
            const clientY = event.clientY ?? (event.touches?.[0]?.clientY ?? 0);
            return {
                x: Math.round(Math.min(100, Math.max(0, ((clientX - rect.left) / rect.width) * 100))),
                y: Math.round(Math.min(100, Math.max(0, ((clientY - rect.top) / rect.height) * 100))),
            };
        },
        setFocal(event) {
            const pos = this.getPosition(event, $refs.container);
            this.focalX = pos.x;
            this.focalY = pos.y;
            $wire.setFocalPoint(pos.x, pos.y);
        },
    }"
    class="mt-4"
>
    <p class="text-sm font-medium text-gray-700 mb-2">Fokální bod</p>
    <p class="text-xs text-gray-500 mb-2">Kliknutím nastavíte střed ořezu pro náhledy.</p>

    <div
        x-ref="container"
        class="relative cursor-crosshair rounded overflow-hidden bg-gray-100"
        style="max-height: 200px;"
        @click="setFocal($event)"
        @touchend.prevent="setFocal($event)"
    >
        @php
            $record = $this->getRecord();
            $imgUrl = $record->hasGeneratedConversion('medium') ? $record->getUrl('medium') : $record->getUrl();
        @endphp
        <img
            src="{{ $imgUrl }}"
            class="w-full h-48 object-cover select-none"
            draggable="false"
            alt=""
        />
        {{-- Focal point indicator --}}
        <div
            class="absolute w-6 h-6 -translate-x-1/2 -translate-y-1/2 pointer-events-none"
            :style="`left: ${focalX}%; top: ${focalY}%;`"
        >
            <div class="w-6 h-6 rounded-full border-2 border-white shadow-lg bg-primary-500/40 ring-1 ring-primary-500"></div>
        </div>
    </div>

    <p class="text-xs text-gray-400 mt-1">Poloha: <span x-text="focalX"></span>% / <span x-text="focalY"></span>%</p>
</div>
