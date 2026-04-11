<?php

declare(strict_types=1);

namespace MiPress\Core\Jobs;

use App\Models\User;
use Awcodes\Curator\Models\Media;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MiPress\Core\Services\CurationGenerator;

class RegenerateCurationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int>|null  $mediaIds
     */
    public function __construct(
        public readonly ?array $mediaIds,
        public readonly int $userId,
    ) {}

    public function handle(CurationGenerator $generator): void
    {
        $processed = 0;
        $skipped = 0;

        $query = Media::query();

        if ($this->mediaIds !== null) {
            $query->whereIn('id', $this->mediaIds);
        } else {
            $query->whereIn('type', $generator->rasterMimeTypes());
        }

        $query
            ->orderBy('id')
            ->chunkById(100, function ($mediaChunk) use ($generator, &$processed, &$skipped): void {
                $mediaChunk->each(function (Media $media) use ($generator, &$processed, &$skipped): void {
                    if ($generator->isRasterImage($media)) {
                        $generator->regenerate($media);
                        $processed++;
                    } else {
                        $skipped++;
                    }
                });
            });

        $recipient = User::find($this->userId);

        if (! $recipient) {
            return;
        }

        $body = "Přegenerováno: {$processed}";

        if ($skipped > 0) {
            $body .= ", přeskočeno (nerastr): {$skipped}";
        }

        Notification::make()
            ->title($this->mediaIds === null
                ? 'Přegenerování ořezů knihovny bylo dokončeno'
                : 'Přegenerování vybraných ořezů bylo dokončeno')
            ->body($body)
            ->success()
            ->sendToDatabase($recipient);
    }
}
