<?php

declare(strict_types=1);

namespace MiPress\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MiPress\Core\Models\CuratorMedia;
use MiPress\Core\Services\FocalPointCropper;

class GenerateCurationsJob implements ShouldQueue
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

    public function handle(FocalPointCropper $cropper): void
    {
        if (! is_media_resizable($this->media->ext)) {
            return;
        }

        $curations = $cropper->generateAll($this->media);

        $this->media->updateQuietly(['curations' => $curations]);
    }
}
