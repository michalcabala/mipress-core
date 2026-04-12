<?php

declare(strict_types=1);

namespace MiPress\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MiPress\Core\Models\Media;
use MiPress\Core\Services\MediaConversionService;

class RegenerateMediaConversionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<int>  $mediaIds
     */
    public function __construct(private readonly array $mediaIds) {}

    public function handle(MediaConversionService $service): void
    {
        $mediaItems = Media::query()
            ->whereIn('id', $this->mediaIds)
            ->get();

        $service->regenerateMany($mediaItems);
    }
}
