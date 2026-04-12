@php
    /** @var \MiPress\Core\Models\Media|null $record */
    $record = $getRecord();

    $conversions = [];

    if ($record instanceof \MiPress\Core\Models\Media && $record->isImage()) {
        $conversions = collect(\MiPress\Core\Media\MediaConfig::conversionsForJs())
            ->map(static function (array $conversion) use ($record): array {
                $name = (string) ($conversion['name'] ?? '');
                $hasGeneratedConversion = $name !== '' && $record->hasGeneratedConversion($name);

                $conversion['url'] = $hasGeneratedConversion ? $record->getFullUrl($name) : null;
                $conversion['version'] = (int) ($record->updated_at?->timestamp ?? 0);
                $conversion['edit_action'] = (($conversion['mode'] ?? null) === 'crop' && $name !== '')
                    ? 'editConversion_'.$name
                    : null;

                return $conversion;
            })
            ->values()
            ->all();
    }
@endphp

@if ($record instanceof \MiPress\Core\Models\Media && $record->isImage())
    <x-mipress::focal-point-picker
        :media="$record"
        :image-url="mipress_media_url($record)"
        :conversions="$conversions"
    />
@endif
