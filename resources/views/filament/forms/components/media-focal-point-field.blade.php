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
                $conversion['edit_action'] = (
                    \MiPress\Core\Media\MediaConfig::usesCropMode((string) ($conversion['mode'] ?? null))
                    && (bool) ($conversion['supports_manual_crop'] ?? false)
                    && $name !== ''
                )
                    ? 'editConversion_'.$name
                    : null;
                $conversion['has_manual_override'] = $name !== '' && $record->hasManualConversionOverride($name);

                return $conversion;
            })
            ->values()
            ->all();
    }
@endphp

<x-mipress::focal-point-picker
    :media="$record"
    :image-url="$record instanceof \MiPress\Core\Models\Media ? mipress_media_url($record) : null"
    :conversions="$conversions"
    x-state-path="data.focal_point_x"
    y-state-path="data.focal_point_y"
    :x-value="$record instanceof \MiPress\Core\Models\Media && is_numeric($record->focal_point_x) ? (int) $record->focal_point_x : 50"
    :y-value="$record instanceof \MiPress\Core\Models\Media && is_numeric($record->focal_point_y) ? (int) $record->focal_point_y : 50"
/>
@endif
