<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
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

        $actionLabel = $mountedAction->getLabel();

        if ($actionLabel instanceof Htmlable) {
            $actionLabel = strip_tags($actionLabel->toHtml());
        }

        $actionLabel = is_string($actionLabel) && trim($actionLabel) !== ''
            ? trim($actionLabel)
            : 'zvolenou akci';

        Notification::make()
            ->title('Formulář obsahuje chyby')
            ->body('Akci „'.$actionLabel.'“ nebylo možné dokončit. Doplňte povinná pole označená červeně a zkuste to znovu.')
            ->danger()
            ->send();
    }
}
