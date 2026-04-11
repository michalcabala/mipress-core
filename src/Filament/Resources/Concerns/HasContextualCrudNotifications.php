<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

trait HasContextualCrudNotifications
{
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Záznam byl vytvořen')
            ->body($this->getContextualCrudNotificationBody());
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Změny byly uloženy')
            ->body($this->getContextualCrudNotificationBody());
    }

    protected function getContextualCrudNotificationBody(): ?string
    {
        if (! method_exists($this, 'getRecord')) {
            return null;
        }

        $record = $this->getRecord();

        if (! $record instanceof Model || ! $record->exists) {
            return null;
        }

        $label = static::getResource()::getTitleCaseModelLabel();
        $title = $this->resolveContextualCrudNotificationRecordTitle($record);

        if (blank($title)) {
            return $label.' #'.$record->getKey();
        }

        return $label.': '.$title;
    }

    protected function resolveContextualCrudNotificationRecordTitle(Model $record): ?string
    {
        $resource = static::getResource();

        if ($resource::hasRecordTitle()) {
            $title = $resource::getRecordTitle($record);

            if ($title instanceof Htmlable) {
                $title = strip_tags($title->toHtml());
            }

            if (is_scalar($title) && trim((string) $title) !== '') {
                return trim((string) $title);
            }
        }

        foreach (['title', 'name', 'handle', 'email', 'slug'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
