<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Blendbyte\FilamentResourceLock\ResourceLockPlugin;
use Livewire\Attributes\On;

trait HandlesResourceLockRenewal
{
    #[On('resourceLockObserver::renewLock')]
    public function renewLock(): void
    {
        $record = $this->record ?? $this->resourceRecord ?? null;

        if (! $record) {
            return;
        }

        if ($record->isUnlocked() || $record->hasExpiredLock()) {
            if ($record->hasExpiredLock()) {
                $record->unlock();
            }

            $record->lock();
            $this->isReadOnly = false;
            $this->resourceLockOwner = null;
        } elseif ($record->isLockedByCurrentUser()) {
            $record->lock();
        } elseif (ResourceLockPlugin::get()->shouldUseReadOnlyMode()) {
            $this->getResourceLockOwner();
            $this->isReadOnly = true;
        } else {
            $this->openLockedResourceModal();
        }

        $this->form->disabled($this->isReadOnly);
        $this->syncReadOnlyNotification();
    }
}
