<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Pages;

use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Pages\ListMedia as BaseListMedia;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use MiPress\Core\Jobs\RegenerateCurationsJob;
use MiPress\Core\Services\CurationGenerator;

class ListMedia extends BaseListMedia
{
    public function getHeaderActions(): array
    {
        $parentActions = parent::getHeaderActions();

        return [
            Action::make('regenerate_all_curations')
                ->label('Přegenerovat vše')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => Filament::auth()->user()?->hasPermissionTo('media.update') ?? false)
                ->authorize(fn (): bool => Filament::auth()->user()?->hasPermissionTo('media.update') ?? false)
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat všechny ořezy')
                ->modalDescription('Přegeneruje miniaturní ořezy pro všechny rastrové obrázky v knihovně médií. Pro velký počet souborů bude zpracování probíhat na pozadí.')
                ->modalSubmitActionLabel('Přegenerovat vše')
                ->action(function (): void {
                    $ids = Media::whereIn('type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->pluck('id')
                        ->all();

                    if (empty($ids)) {
                        Notification::make()
                            ->title('Žádné obrázky k přegenerování')
                            ->warning()
                            ->send();

                        return;
                    }

                    $userId = auth()->id();

                    if (count($ids) <= 10) {
                        $generator = app(CurationGenerator::class);
                        $processed = 0;

                        Media::whereIn('id', $ids)->get()->each(
                            function (Media $media) use ($generator, &$processed): void {
                                $generator->regenerate($media);
                                $processed++;
                            }
                        );

                        Notification::make()
                            ->title('Ořezy přegenerovány')
                            ->body("Zpracováno {$processed} obrázků.")
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
            ...$parentActions,
        ];
    }
}
