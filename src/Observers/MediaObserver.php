<?php

declare(strict_types=1);

namespace MiPress\Core\Observers;

use Awcodes\Curator\Models\Media;
use MiPress\Core\Services\CurationGenerator;

class MediaObserver
{
    public function creating(Media $media): void
    {
        if (auth()->check()) {
            $media->uploaded_by = auth()->id();
        }
    }

    public function created(Media $media): void
    {
        app(CurationGenerator::class)->generateOnUpload($media);
    }

    public function deleted(Media $media): void
    {
        app(CurationGenerator::class)->deleteCurationFiles($media);
    }
}
