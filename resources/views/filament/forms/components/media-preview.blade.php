@php
    /** @var \MiPress\Core\Models\Media|null $record */
    $record = $getRecord();
    $isImage = $record instanceof \MiPress\Core\Models\Media && $record->isImage();
    $imageUrl = $isImage ? mipress_media_url($record) : null;
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-gray-900">
    @if ($isImage && $imageUrl)
        <img
            src="{{ $imageUrl }}?v={{ $record->updated_at?->timestamp ?? 0 }}"
            alt="{{ $record->alt ?? $record->file_name }}"
            class="h-auto max-h-[32rem] w-full object-contain"
        >
    @else
        <div class="flex min-h-48 items-center justify-center p-8">
            <div class="text-center">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $record?->file_name ?? 'Žádný soubor' }}</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $record?->mime_type ?? '' }}</p>
            </div>
        </div>
    @endif
</div>
