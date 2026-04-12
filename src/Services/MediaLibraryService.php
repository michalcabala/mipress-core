<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Attachment;
use MiPress\Core\Models\Media;

class MediaLibraryService
{
    /**
     * @param  array<int, string>|string|null  $paths
     * @return list<int>
     */
    public function createFromTemporaryPaths(array|string|null $paths, ?int $uploadedBy = null): array
    {
        $mediaIds = [];

        foreach (Arr::wrap($paths) as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $attachment = Attachment::query()->create([
                'name' => pathinfo($path, PATHINFO_FILENAME),
            ]);

            /** @var Media $media */
            $media = $attachment
                ->addMediaFromDisk($path, MediaConfig::disk())
                ->usingName(pathinfo($path, PATHINFO_FILENAME))
                ->toMediaCollection(MediaConfig::libraryCollection(), MediaConfig::disk());

            if ($uploadedBy !== null && $media->uploaded_by === null) {
                $media->forceFill([
                    'uploaded_by' => $uploadedBy,
                ])->saveQuietly();
            }

            Storage::disk(MediaConfig::disk())->delete($path);

            $mediaIds[] = (int) $media->getKey();
        }

        return $mediaIds;
    }
}
