<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CuratorMediaResource\Pages;

use Awcodes\Curator\Resources\Media\Pages\EditMedia;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use MiPress\Core\Filament\Resources\CuratorMediaResource;
use MiPress\Core\Models\CuratorMedia;
use MiPress\Core\Services\FocalPointCropper;

class EditCuratorMedia extends EditMedia
{
    protected static string $resource = CuratorMediaResource::class;

    /**
     * Track original focal point values to detect changes.
     *
     * @var array{x: int, y: int}
     */
    protected array $originalFocalPoint = ['x' => 50, 'y' => 50];

    protected function afterFill(): void
    {
        /** @var CuratorMedia $record */
        $record = $this->record;

        $this->originalFocalPoint = [
            'x' => $record->focal_point_x ?? 50,
            'y' => $record->focal_point_y ?? 50,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->action('save')
                ->label(trans('curator::views.panel.edit_save')),
            Action::make('regenerate_curations')
                ->label(__('mipress::admin.curator_media.actions.regenerate'))
                ->icon('far-arrows-rotate')
                ->color('warning')
                ->visible(fn (): bool => $this->record && is_media_resizable($this->record->ext))
                ->requiresConfirmation()
                ->modalHeading(__('mipress::admin.curator_media.actions.regenerate'))
                ->modalDescription(__('mipress::admin.curator_media.actions.regenerate_modal_description'))
                ->action(function (): void {
                    $this->regenerateCurations(redirect: true);
                }),
            Action::make('preview')
                ->color('gray')
                ->url($this->record->url, shouldOpenInNewTab: true)
                ->label(trans('curator::views.panel.view')),
            DeleteAction::make(),
            Action::make('cancel')
                ->label(__('mipress::admin.curator_media.actions.cancel'))
                ->color('gray')
                ->url(static::getResource()::getUrl()),
        ];
    }

    protected function afterSave(): void
    {
        parent::afterSave();

        /** @var CuratorMedia $record */
        $record = $this->record->fresh();

        $newX = $record->focal_point_x ?? 50;
        $newY = $record->focal_point_y ?? 50;

        if ($newX !== $this->originalFocalPoint['x'] || $newY !== $this->originalFocalPoint['y']) {
            $this->regenerateCurations(notify: true, redirect: true);
        }
    }

    protected function regenerateCurations(bool $notify = true, bool $redirect = false): void
    {
        /** @var CuratorMedia $record */
        $record = $this->record->fresh();

        $cropper = app(FocalPointCropper::class);
        $curations = $cropper->generateAll($record);

        $record->update(['curations' => $curations]);

        if ($notify) {
            $count = count($curations);
            Notification::make()
                ->title(__('mipress::admin.curator_media.actions.regenerated_title', ['count' => $count]))
                ->body(__('mipress::admin.curator_media.actions.regenerated_body', ['x' => $record->focal_point_x, 'y' => $record->focal_point_y]))
                ->success()
                ->send();
        }

        if ($redirect) {
            $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));
        }
    }
}
