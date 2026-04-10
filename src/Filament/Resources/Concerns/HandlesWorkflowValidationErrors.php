<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait HandlesWorkflowValidationErrors
{
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $mountedAction = $this->getMountedAction();

        if (! $mountedAction instanceof Action) {
            return;
        }

        if ($this->mountedActionShouldOpenModal($mountedAction)) {
            $this->unmountAction(canCancelParentActions: false);
        }

        Notification::make()
            ->title('Formulář obsahuje chyby')
            ->body('Doplňte povinná pole označená červeně a akci opakujte.')
            ->danger()
            ->send();
    }
}
