<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Pages;

use Awcodes\Curator\Resources\Media\Pages\EditMedia as BaseEditMedia;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use MiPress\Core\Services\MediaCurationOrchestrator;

class EditMedia extends BaseEditMedia
{
    public function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->action('save')
                ->label(trans('curator::views.panel.edit_save')),
            Action::make('regenerate_curations')
                ->label('Přegenerovat ořezy')
                ->icon('fal-arrows-rotate')
                ->color('gray')
                ->visible(fn (): bool => Filament::auth()->user()?->can('regenerateSingleCuration', $this->getRecord()) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat ořezy')
                ->modalDescription('Přegeneruje všechny miniaturní ořezy pro tento soubor. Stávající ořezy budou přepsány.')
                ->modalSubmitActionLabel('Přegenerovat')
                ->action(function (): void {
                    $media = $this->getRecord();

                    if (! app(MediaCurationOrchestrator::class)->regenerateSingle($media)) {
                        Notification::make()
                            ->title('Přegenerování nelze provést')
                            ->body('Soubor není rastrový obrázek.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $generator->regenerate($media);

                    Notification::make()
                        ->title('Ořezy přegenerovány')
                        ->success()
                        ->send();
                }),
            Action::make('preview')
                ->color('gray')
                ->url($this->record->url, shouldOpenInNewTab: true)
                ->label(trans('curator::views.panel.view')),
            DeleteAction::make(),
        ];
    }
}
