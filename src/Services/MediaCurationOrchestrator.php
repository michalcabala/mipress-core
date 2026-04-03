<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Models\Media;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use MiPress\Core\Jobs\RegenerateCurationsJob;

class MediaCurationOrchestrator
{
    private const SYNC_THRESHOLD = 10;

    public function __construct(
        private readonly CurationGenerator $generator,
    ) {}

    public function regenerateSingle(Media $media): bool
    {
        if (! $this->generator->isRasterImage($media)) {
            return false;
        }

        $this->generator->regenerate($media);

        return true;
    }

    public function regenerateSelected(Collection $records, int $userId): void
    {
        $ids = $records->modelKeys();

        if ($ids === []) {
            Notification::make()
                ->title('Žádné obrázky k přegenerování')
                ->warning()
                ->send();

            return;
        }

        if (count($ids) <= self::SYNC_THRESHOLD) {
            $processed = 0;

            $records->each(function (Media $media) use (&$processed): void {
                if ($this->regenerateSingle($media)) {
                    $processed++;
                }
            });

            Notification::make()
                ->title('Ořezy přegenerovány')
                ->body("Zpracováno {$processed} z ".count($ids).' souborů.')
                ->success()
                ->send();

            return;
        }

        RegenerateCurationsJob::dispatch($ids, $userId);

        Notification::make()
            ->title('Přegenerování zařazeno do fronty')
            ->body('Ořezy budou přegenerovány na pozadí. Po dokončení obdržíte oznámení.')
            ->info()
            ->send();
    }

    public function regenerateAll(int $userId): void
    {
        $query = Media::query()->whereIn('type', $this->generator->rasterMimeTypes());

        $total = (clone $query)->count();

        if ($total === 0) {
            Notification::make()
                ->title('Žádné obrázky k přegenerování')
                ->warning()
                ->send();

            return;
        }

        if ($total <= self::SYNC_THRESHOLD) {
            $processed = 0;

            $query->get()->each(function (Media $media) use (&$processed): void {
                $this->generator->regenerate($media);
                $processed++;
            });

            Notification::make()
                ->title('Ořezy přegenerovány')
                ->body("Zpracováno {$processed} obrázků.")
                ->success()
                ->send();

            return;
        }

        RegenerateCurationsJob::dispatch(null, $userId);

        Notification::make()
            ->title('Přegenerování zařazeno do fronty')
            ->body('Ořezy budou přegenerovány na pozadí. Po dokončení obdržíte oznámení.')
            ->info()
            ->send();
    }
}
