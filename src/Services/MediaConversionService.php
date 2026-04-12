<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Media;
use Spatie\MediaLibrary\Conversions\FileManipulator;

class MediaConversionService
{
    public function __construct(private readonly FileManipulator $fileManipulator) {}

    public function regenerate(Media $media): bool
    {
        if (! $media->isImage()) {
            return false;
        }

        $this->fileManipulator->createDerivedFiles(
            $media,
            MediaConfig::conversionNames(),
            onlyMissing: false,
            withResponsiveImages: false,
            queueAll: true,
        );

        return true;
    }

    /**
     * @param  iterable<Media>  $mediaItems
     */
    public function regenerateMany(iterable $mediaItems): int
    {
        $count = 0;

        foreach ($mediaItems as $media) {
            if (! $media instanceof Media) {
                continue;
            }

            if ($this->regenerate($media)) {
                $count++;
            }
        }

        return $count;
    }

    public function regenerateQuery(Builder $query): int
    {
        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = $query->get();

        return $this->regenerateMany($mediaItems);
    }
}
