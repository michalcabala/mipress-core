@php
    $record = $getRecord();
    $ext = $record->ext;
    $isPreviewable = curator()->isPreviewable($ext);
    $isSvg = curator()->isSvg($ext);

    $src = $record->thumbnail_curation_url ?? $record->mediumUrl;
@endphp

<div {{ $attributes->merge($getExtraAttributes())->class(['curator-grid-column absolute inset-0 rounded-t-xl overflow-hidden']) }}>
    <div @class([
        'rounded-t-xl h-full overflow-hidden bg-gray-100 dark:bg-gray-950/50',
        'checkered' => $isSvg,
    ])>
        @if ($isPreviewable)
            <img
                src="{{ $src }}"
                alt="{{ $record->alt ?? '' }}"
                loading="lazy"
                @class([
                    'h-full object-cover',
                    'w-auto mx-auto p-2' => $isSvg,
                    'w-full' => ! $isSvg,
                ])
            />
        @elseif (curator()->isVideo($ext))
            <div class="grid place-items-center w-full h-full text-xs uppercase relative">
                @svg('far-film', 'size-16 opacity-20')
                <span class="block absolute text-gray-500 dark:text-gray-400 font-medium">{{ strtoupper($ext) }}</span>
            </div>
        @elseif (curator()->isAudio($ext))
            <div class="grid place-items-center w-full h-full text-xs uppercase relative">
                @svg('far-waveform', 'size-16 opacity-20')
                <span class="block absolute text-gray-500 dark:text-gray-400 font-medium">{{ strtoupper($ext) }}</span>
            </div>
        @else
            <div class="grid place-items-center w-full h-full text-xs uppercase relative">
                @svg('far-file', 'size-16 opacity-20')
                <span class="block absolute text-gray-500 dark:text-gray-400 font-medium">{{ strtoupper($ext) }}</span>
            </div>
        @endif

        <div class="absolute inset-x-0 bottom-0 flex items-center justify-between px-1.5 pt-10 pb-1.5 text-xs text-white bg-gradient-to-t from-black/80 to-transparent gap-3 pointer-events-none">
            <p class="truncate">{{ $record->pretty_name }}</p>
            <p class="flex-shrink-0">{{ curator()->sizeForHumans($record->size) }}</p>
        </div>
    </div>
</div>
