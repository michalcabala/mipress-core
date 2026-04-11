<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use MiPress\Core\Enums\EntryStatus;

trait HasReactivePublicationFields
{
    protected static function configureReactivePublicationStatusField(ToggleButtons $field, bool $canPublish): ToggleButtons
    {
        if (! $canPublish) {
            return $field;
        }

        return $field
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                $status = static::normalizePublicationStatus($state);

                if ($status === null) {
                    return;
                }

                $publishedAt = static::normalizePublicationDate($get('published_at'));

                if ($status === EntryStatus::Published && ($publishedAt === null || $publishedAt->isFuture())) {
                    $set('published_at', now()->startOfMinute());

                    return;
                }

                if ($status === EntryStatus::Scheduled && ! ($publishedAt instanceof CarbonInterface && $publishedAt->isFuture())) {
                    $set('published_at', static::defaultScheduledPublicationAt());
                }
            });
    }

    protected static function configureReactivePublicationDateField(DateTimePicker $field, bool $canPublish): DateTimePicker
    {
        $field = $field->native(false);

        if (! $canPublish) {
            return $field;
        }

        return $field
            ->live()
            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                $publishedAt = static::normalizePublicationDate($state);

                if ($publishedAt === null) {
                    return;
                }

                $currentStatus = static::normalizePublicationStatus($get('status'));

                if ($publishedAt->isFuture()) {
                    if (! in_array($currentStatus, [EntryStatus::InReview, EntryStatus::Rejected], true)) {
                        $set('status', EntryStatus::Scheduled->value);
                    }

                    return;
                }

                if ($currentStatus === EntryStatus::Scheduled) {
                    $set('status', EntryStatus::Published->value);
                }
            });
    }

    private static function defaultScheduledPublicationAt(): CarbonInterface
    {
        return now()->addHour()->startOfHour();
    }

    private static function normalizePublicationStatus(mixed $value): ?EntryStatus
    {
        if ($value instanceof EntryStatus) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return EntryStatus::tryFrom($value);
    }

    private static function normalizePublicationDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
