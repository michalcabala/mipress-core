<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Tables;

use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Tables\MediaTable as BaseMediaTable;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use MiPress\Core\Jobs\RegenerateCurationsJob;
use MiPress\Core\Services\CurationGenerator;

class MediaTable extends BaseMediaTable
{
    public static function configure(Table $table): Table
    {
        $table = parent::configure($table);

        return $table->pushToolbarActions([
            BulkAction::make('regenerate_curations')
                ->label('Přegenerovat ořezy')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('regenerateCurations', Media::class))
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat ořezy')
                ->modalDescription('Přegeneruje miniaturní ořezy pro vybrané soubory. Rastrové obrázky budou přepsány novými ořezy.')
                ->modalSubmitActionLabel('Přegenerovat')
                ->action(function (Collection $records): void {
                    $ids = $records->modelKeys();
                    $userId = auth()->id();

                    if (count($ids) <= 10) {
                        $generator = app(CurationGenerator::class);
                        $processed = 0;

                        $records->each(function ($media) use ($generator, &$processed): void {
                            if ($generator->isRasterImage($media)) {
                                $generator->regenerate($media);
                                $processed++;
                            }
                        });

                        Notification::make()
                            ->title('Ořezy přegenerovány')
                            ->body("Zpracováno {$processed} z ".count($ids).' souborů.')
                            ->success()
                            ->send();
                    } else {
                        RegenerateCurationsJob::dispatch($ids, $userId);

                        Notification::make()
                            ->title('Přegenerování zařazeno do fronty')
                            ->body('Ořezy budou přegenerovány na pozadí. Po dokončení obdržíte oznámení.')
                            ->info()
                            ->send();
                    }
                }),
        ]);
    }
}
