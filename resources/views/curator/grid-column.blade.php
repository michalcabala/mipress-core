@php
    $record = $getRecord();
    $ext = $record->ext;
    $isPreviewable = curator()->isPreviewable($ext);
    $isSvg = curator()->isSvg($ext);
    $src = $record->thumbnailUrl;
@endphp

<div {{ $attributes->merge($getExtraAttributes())->class(['aspect-square overflow-hidden bg-gray-100 dark:bg-gray-950/50']) }}>
    @if ($isPreviewable)
        <img
            src="{{ $src }}"
            alt="{{ $record->alt ?? '' }}"
            loading="lazy"
            @class([
                'w-full h-full',
                'object-contain p-2' => $isSvg,
                'object-cover' => ! $isSvg,
            ])
        />
    @elseif (curator()->isVideo($ext))
        <div class="grid place-items-center w-full h-full relative">
            @svg('far-film', 'size-10 opacity-20')
            <span class="absolute text-gray-500 dark:text-gray-400 font-medium text-[10px] uppercase">{{ $ext }}</span>
        </div>
    @elseif (curator()->isAudio($ext))
        <div class="grid place-items-center w-full h-full relative">
            @svg('far-waveform', 'size-10 opacity-20')
            <span class="absolute text-gray-500 dark:text-gray-400 font-medium text-[10px] uppercase">{{ $ext }}</span>
        </div>
    @else
        <div class="grid place-items-center w-full h-full relative">
            @svg('far-file', 'size-10 opacity-20')
            <span class="absolute text-gray-500 dark:text-gray-400 font-medium text-[10px] uppercase">{{ $ext }}</span>
        </div>
    @endif
</div>
