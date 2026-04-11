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
                ->title('Nebyl vybrán žádný obrázek k přegenerování')
                ->body('Vyberte alespoň jeden rastrový obrázek, pro který chcete znovu vytvořit ořezy.')
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
                ->title('Ořezy vybraných souborů byly přegenerovány')
                ->body('Vybráno '.count($ids).' souborů, přegenerováno '.$processed.'.')
                ->success()
                ->send();

            return;
        }

        RegenerateCurationsJob::dispatch($ids, $userId);

        Notification::make()
            ->title('Přegenerování vybraných ořezů bylo zařazeno do fronty')
            ->body('Vybráno '.count($ids).' souborů. Ořezy budou přegenerovány na pozadí a po dokončení obdržíte oznámení.')
            ->info()
            ->send();
    }

    public function regenerateAll(int $userId): void
    {
        $query = Media::query()->whereIn('type', $this->generator->rasterMimeTypes());

        $total = (clone $query)->count();

        if ($total === 0) {
            Notification::make()
                ->title('V knihovně nejsou žádné obrázky k přegenerování')
                ->body('Knihovna médií momentálně neobsahuje žádný rastrový obrázek.')
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
                ->title('Ořezy knihovny médií byly přegenerovány')
                ->body('Přegenerováno bylo '.$processed.' obrázků z knihovny médií.')
                ->success()
                ->send();

            return;
        }

        RegenerateCurationsJob::dispatch(null, $userId);

        Notification::make()
            ->title('Přegenerování ořezů knihovny bylo zařazeno do fronty')
            ->body('Celá knihovna médií se zpracuje na pozadí. Po dokončení obdržíte oznámení.')
            ->info()
            ->send();
    }
}
