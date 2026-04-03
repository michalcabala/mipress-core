<?php

declare(strict_types=1);

namespace MiPress\Core\Observers;

use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Services\CurationGenerator;

class MediaObserver
{
    public function created(Media $media): void
    {
        app(CurationGenerator::class)->generateOnUpload($media);
    }

    public function deleted(Media $media): void
    {
        if (blank($media->curations)) {
            return;
        }

        $storage = Storage::disk($media->disk);

        foreach ($media->curations as $item) {
            $path = $item['curation']['path'] ?? null;

            if ($path && $storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }
}
