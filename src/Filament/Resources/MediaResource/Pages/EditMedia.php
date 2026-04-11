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
                ->modalHeading(fn (): string => 'Přegenerovat ořezy souboru "'.$this->getRecord()->name.'"?')
                ->modalDescription('Pro soubor "'.$this->getRecord()->name.'" se znovu vytvoří všechny miniaturní ořezy a původní varianty se přepíšou.')
                ->modalSubmitActionLabel('Přegenerovat')
                ->action(function (): void {
                    $media = $this->getRecord();

                    if (! app(MediaCurationOrchestrator::class)->regenerateSingle($media)) {
                        Notification::make()
                            ->title('Přegenerování souboru nelze provést')
                            ->body('Soubor "'.$media->name.'" není rastrový obrázek.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Ořezy souboru byly přegenerovány')
                        ->body('Pro soubor "'.$media->name.'" byly úspěšně vytvořeny nové ořezy.')
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
