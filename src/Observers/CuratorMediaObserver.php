<?php

declare(strict_types=1);

namespace MiPress\Core\Observers;

use Illuminate\Support\Facades\Storage;
use MiPress\Core\Jobs\ConvertMediaToWebpJob;
use MiPress\Core\Models\CuratorMedia;

class CuratorMediaObserver
{
    public function created(CuratorMedia $media): void
    {
        $this->moveToIdSubfolder($media);
        $this->setUploadedBy($media);

        if (is_media_resizable($media->ext)) {
            ConvertMediaToWebpJob::dispatch($media->fresh());
        }
    }

    private function moveToIdSubfolder(CuratorMedia $media): void
    {
        $storage = Storage::disk($media->disk);

        if (! $storage->exists($media->path)) {
            return;
        }

        $newDirectory = $media->directory.'/'.$media->getKey();
        $newPath = $newDirectory.'/'.$media->name.'.'.$media->ext;

        if ($media->path === $newPath) {
            return;
        }

        $storage->move($media->path, $newPath);

        $media->updateQuietly([
            'directory' => $newDirectory,
            'path' => $newPath,
        ]);
    }

    private function setUploadedBy(CuratorMedia $media): void
    {
        if ($media->uploaded_by !== null) {
            return;
        }

        $userId = auth()->id();

        if ($userId !== null) {
            $media->updateQuietly(['uploaded_by' => $userId]);
        }
    }
}
