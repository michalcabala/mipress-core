<?php

declare(strict_types=1);

namespace MiPress\Core\Jobs;

use Awcodes\Curator\Facades\Glide;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Models\CuratorMedia;

class ConvertMediaToWebpJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [5, 15, 30];

    public function __construct(
        public CuratorMedia $media,
    ) {}

    public function handle(): void
    {
        if (! is_media_resizable($this->media->ext)) {
            return;
        }

        if ($this->media->ext === 'webp') {
            GenerateCurationsJob::dispatch($this->media);

            return;
        }

        $storage = Storage::disk($this->media->disk);
        $originalPath = $this->media->path;

        if (! $storage->exists($originalPath)) {
            return;
        }

        $manager = Glide::getServer()->getApi()->getImageManager();
        $image = $manager->read($storage->path($originalPath));
        $image->orient();

        $webpContent = $image->toWebp(quality: 85);

        $webpName = $this->media->name;
        $webpPath = $this->media->directory.'/'.$webpName.'.webp';

        $storage->put($webpPath, $webpContent);

        // Keep original file as backup (renamed with _original suffix)
        $originalBackupPath = $this->media->directory.'/'.$this->media->name.'_original.'.$this->media->ext;
        $storage->move($originalPath, $originalBackupPath);

        $this->media->updateQuietly([
            'path' => $webpPath,
            'ext' => 'webp',
            'type' => 'image/webp',
            'size' => $storage->size($webpPath),
        ]);

        GenerateCurationsJob::dispatch($this->media->fresh());
    }
}
