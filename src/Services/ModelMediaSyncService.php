<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Media;
use MiPress\Core\Models\Page;

class ModelMediaSyncService
{
    public function syncFeaturedImage(Entry|Page $record, ?int $libraryMediaId): void
    {
        if (! $libraryMediaId) {
            $record->clearMediaCollection(MediaConfig::featuredCollection());
            $record->forceFill([
                'featured_image_id' => null,
            ])->saveQuietly();

            return;
        }

        $sourceMedia = Media::query()->libraryItems()->find($libraryMediaId);

        if (! $sourceMedia instanceof Media) {
            return;
        }

        /** @var Media|null $existingMedia */
        $existingMedia = $record->getFirstMedia(MediaConfig::featuredCollection());

        if (
            $existingMedia instanceof Media
            && (int) $existingMedia->getCustomProperty('library_media_id') === $libraryMediaId
        ) {
            if (
                (int) $existingMedia->focal_point_x !== (int) $sourceMedia->focal_point_x
                || (int) $existingMedia->focal_point_y !== (int) $sourceMedia->focal_point_y
            ) {
                $existingMedia->forceFill([
                    'focal_point_x' => $sourceMedia->focal_point_x,
                    'focal_point_y' => $sourceMedia->focal_point_y,
                ])->saveQuietly();

                app(MediaConversionService::class)->regenerate($existingMedia);
            }

            $record->forceFill([
                'featured_image_id' => (int) $existingMedia->getKey(),
            ])->saveQuietly();

            return;
        }

        $record->clearMediaCollection(MediaConfig::featuredCollection());

        $copiedMedia = $sourceMedia->copy(
            $record,
            MediaConfig::featuredCollection(),
            MediaConfig::disk(),
        );

        $copiedMedia->forceFill([
            'focal_point_x' => $sourceMedia->focal_point_x,
            'focal_point_y' => $sourceMedia->focal_point_y,
        ])->saveQuietly();

        $copiedMedia
            ->setCustomProperty('library_media_id', $sourceMedia->getKey())
            ->saveQuietly();

        app(MediaConversionService::class)->regenerate($copiedMedia);

        $record->forceFill([
            'featured_image_id' => (int) $copiedMedia->getKey(),
        ])->saveQuietly();
    }

    /**
     * @param  list<int|string>|null  $libraryMediaIds
     */
    public function syncGallery(Model $record, ?array $libraryMediaIds): void
    {
        if (! method_exists($record, 'clearMediaCollection')) {
            return;
        }

        $record->clearMediaCollection(MediaConfig::galleryCollection());

        $orderedIds = collect($libraryMediaIds ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        foreach ($orderedIds as $index => $mediaId) {
            $sourceMedia = Media::query()->libraryItems()->find($mediaId);

            if (! $sourceMedia instanceof Media) {
                continue;
            }

            $sourceMedia->copy(
                $record,
                MediaConfig::galleryCollection(),
                MediaConfig::disk(),
                fileAdderCallback: fn ($fileAdder) => $fileAdder->setOrder($index + 1),
            );
        }
    }
}
