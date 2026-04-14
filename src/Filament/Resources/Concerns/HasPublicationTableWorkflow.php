<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

/**
 * Shared publication workflow UI for Filament table classes.
 *
 * Includes HasReactivePublicationFields automatically.
 * Requires the using class to implement the abstract configuration methods below.
 */
trait HasPublicationTableWorkflow
{
    use HasReactivePublicationFields;
    // ── Configuration (override in using class) ──

    abstract protected static function getContentModelClass(): string;

    abstract protected static function getPreviewRouteName(): string;

    abstract protected static function getPreviewRouteParameterName(): string;

    abstract protected static function getEditUrl(Model $record): string;

    abstract protected static function getPublishPermission(): string;

    abstract protected static function getContentLabel(): string;

    abstract protected static function getContentLabelPlural(): string;

    // ── Table Actions ──

    protected static function makeViewLiveAction(): Action
    {
        $modelClass = static::getContentModelClass();

        return Action::make('viewLive')
            ->label('Zobrazit na webu')
            ->icon('far-arrow-up-right-from-square')
            ->color('gray')
            ->url(fn (Model $record): ?string => $record->getPublicUrl(), shouldOpenInNewTab: true)
            ->visible(fn (Model $record): bool => $record instanceof $modelClass
                && auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status === EntryStatus::Published
                && filled($record->getPublicUrl()));
    }

    protected static function makePreviewAction(): Action
    {
        $modelClass = static::getContentModelClass();

        return Action::make('preview')
            ->label('Náhled')
            ->icon('far-eye')
            ->color('gray')
            ->url(
                fn (Model $record): string => URL::temporarySignedRoute(
                    static::getPreviewRouteName(),
                    now()->addHour(),
                    [static::getPreviewRouteParameterName() => $record->getKey()],
                ),
                shouldOpenInNewTab: true,
            )
            ->visible(fn (Model $record): bool => $record instanceof $modelClass
                && auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status !== EntryStatus::Published);
    }

    protected static function makeTogglePublicationAction(): Action
    {
        $modelClass = static::getContentModelClass();

        return Action::make('togglePublicationStatus')
            ->label('Změnit publikaci')
            ->icon('far-arrows-rotate')
            ->color('gray')
            ->visible(fn (Model $record): bool => $record instanceof $modelClass
                && auth()->user()?->can('publish', $record) === true
                && ! $record->trashed())
            ->modalHeading(fn (Model $record): string => 'Změnit publikaci: '.$record->title)
            ->modalSubmitActionLabel('Uložit změny')
            ->fillForm(fn (Model $record): array => [
                'status' => $record->status->value,
                'published_at' => $record->scheduled_at ?? $record->published_at,
            ])
            ->schema(fn (Model $record): array => static::getPublicationWorkflowSchema($record))
            ->action(function (Model $record, array $data, $livewire): void {
                $previousStatus = $record->status;

                if (! static::applyPublicationWorkflowData($record, $data)) {
                    Notification::make()
                        ->title('Bez změny')
                        ->warning()
                        ->send();

                    return;
                }

                static::sendReviewRequestedNotificationIfNeeded($record, $previousStatus);

                Notification::make()
                    ->title(static::getPublicationNotificationTitle($previousStatus, $record->status))
                    ->body(static::getPublicationNotificationBody($record))
                    ->success()
                    ->send();

                if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch('entry-publication-status-updated');
                }
            });
    }

    protected static function makeBulkPublicationAction(): BulkAction
    {
        $modelClass = static::getContentModelClass();
        $label = static::getContentLabelPlural();

        return BulkAction::make('changePublicationStatus')
            ->label('Změnit publikaci')
            ->icon('far-arrows-rotate')
            ->visible(fn (): bool => auth()->user()?->hasPermissionTo(static::getPublishPermission()) === true)
            ->modalHeading("Změnit publikaci vybraných {$label}")
            ->modalSubmitActionLabel('Uložit změny')
            ->schema(static::getPublicationWorkflowSchema())
            ->action(function (EloquentCollection $records, array $data, $livewire) use ($modelClass): void {
                $updated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof $modelClass || auth()->user()?->can('publish', $record) !== true) {
                        $skipped++;

                        continue;
                    }

                    $previousStatus = $record->status;
                    $statusChanged = static::applyPublicationWorkflowData($record, $data);

                    if ($statusChanged) {
                        $updated++;

                        static::sendReviewRequestedNotificationIfNeeded($record, $previousStatus);

                        continue;
                    }

                    $skipped++;
                }

                Notification::make()
                    ->title($updated > 0 ? 'Stav publikace změněn' : 'Bez změny')
                    ->body("Aktualizováno {$updated} položek, přeskočeno {$skipped}.")
                    ->{$updated > 0 ? 'success' : 'warning'}()
                    ->send();

                if (($updated > 0) && is_object($livewire) && method_exists($livewire, 'dispatch')) {
                    $livewire->dispatch('entry-publication-status-updated');
                }
            });
    }

    // ── Workflow Schema ──

    /**
     * @return array<int, ToggleButtons|DateTimePicker>
     */
    protected static function getPublicationWorkflowSchema(?Model $record = null): array
    {
        return [
            static::makePublicationStatusField($record),
            static::makePublicationDateField($record),
        ];
    }

    protected static function makePublicationStatusField(?Model $record): ToggleButtons
    {
        return self::configureReactivePublicationStatusField(
            ToggleButtons::make('status')
                ->label('Stav publikování')
                ->options(static::getPublicationStatusOptions($record))
                ->colors(static::getPublicationStatusColors())
                ->icons(static::getPublicationStatusIcons())
                ->inline()
                ->required()
                ->helperText(static::publicationStatusHelperText($record)),
            static::canPublishRecord($record),
        );
    }

    protected static function makePublicationDateField(?Model $record): DateTimePicker
    {
        return self::configureReactivePublicationDateField(
            DateTimePicker::make('published_at')
                ->label('Datum publikace')
                ->nullable()
                ->disabled(fn (): bool => ! static::canPublishRecord($record))
                ->helperText('Pokud nastavíte budoucí datum a čas, obsah se uloží jako naplánovaný.'),
            static::canPublishRecord($record),
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function getPublicationStatusOptions(?Model $record): array
    {
        return collect(static::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    protected static function getVisiblePublicationStatuses(?Model $record): array
    {
        $modelClass = static::getContentModelClass();

        if (static::canPublishRecord($record)) {
            return EntryStatus::cases();
        }

        if (! $record instanceof $modelClass) {
            return [EntryStatus::Draft, EntryStatus::InReview];
        }

        return match ($record->status) {
            EntryStatus::Published, EntryStatus::Scheduled => [$record->status, EntryStatus::InReview],
            EntryStatus::Rejected => [$record->status, EntryStatus::Draft, EntryStatus::InReview],
            default => [EntryStatus::Draft, EntryStatus::InReview],
        };
    }

    /**
     * @return array<string, string|array|null>
     */
    protected static function getPublicationStatusColors(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getColor()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    protected static function getPublicationStatusIcons(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getIcon()])
            ->all();
    }

    protected static function publicationStatusHelperText(?Model $record): string
    {
        $modelClass = static::getContentModelClass();

        if (static::canPublishRecord($record)) {
            return 'Budoucí datum a čas uloží obsah jako naplánovaný.';
        }

        if ($record instanceof $modelClass && in_array($record->status, [EntryStatus::Published, EntryStatus::Scheduled], true)) {
            return 'Po uložení budou změny odeslány ke schválení.';
        }

        return 'Vyberte, zda obsah uložit jako koncept nebo odeslat ke schválení.';
    }

    protected static function canPublishRecord(?Model $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        $modelClass = static::getContentModelClass();

        if ($record instanceof $modelClass) {
            return $user->can('publish', $record);
        }

        return $user->hasPermissionTo(static::getPublishPermission());
    }

    // ── Workflow Data Application ──

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function applyPublicationWorkflowData(Model $record, array $data): bool
    {
        $preparedData = app(WorkflowTransitionService::class)->prepareFormDataForStatus(
            $data,
            canPublish: static::canPublishRecord($record),
            currentStatus: $record->status,
        );

        $nextStatus = data_get($preparedData, 'status');
        $nextStatus = $nextStatus instanceof EntryStatus
            ? $nextStatus
            : EntryStatus::tryFrom((string) $nextStatus);

        if (! $nextStatus instanceof EntryStatus) {
            return false;
        }

        $currentPublishedAt = static::normalizePublicationDateValue($record->published_at);
        $currentScheduledAt = static::normalizePublicationDateValue($record->scheduled_at);
        $nextPublishedAt = static::normalizePublicationDateValue(data_get($preparedData, 'published_at'));
        $nextScheduledAt = static::normalizePublicationDateValue(data_get($preparedData, 'scheduled_at'));
        $nextReviewNote = data_get($preparedData, 'review_note');

        $hasChanged = $record->status !== $nextStatus
            || $currentPublishedAt?->format('c') !== $nextPublishedAt?->format('c')
            || $currentScheduledAt?->format('c') !== $nextScheduledAt?->format('c')
            || (string) ($record->review_note ?? '') !== (string) ($nextReviewNote ?? '');

        if (! $hasChanged) {
            return false;
        }

        $record->status = $nextStatus;
        $record->published_at = $nextPublishedAt;
        $record->scheduled_at = $nextScheduledAt;
        $record->review_note = $nextReviewNote;
        $record->save();

        return true;
    }

    protected static function normalizePublicationDateValue(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    // ── Notifications ──

    protected static function sendReviewRequestedNotificationIfNeeded(Model $record, EntryStatus $previousStatus): void
    {
        if ($previousStatus === $record->status || $record->status !== EntryStatus::InReview) {
            return;
        }

        $contentLabel = static::getContentLabel();

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: static::getPublishPermission(),
            title: "Nový obsah ke schválení",
            body: "{$contentLabel} \"{$record->title}\" čeká na schválení publikace.",
            editUrl: static::getEditUrl($record),
            previewRouteName: static::getPreviewRouteName(),
            previewRouteParameterName: static::getPreviewRouteParameterName(),
        );
    }

    protected static function getPublicationNotificationTitle(EntryStatus $previousStatus, EntryStatus $currentStatus): string
    {
        $label = static::getContentLabel();

        return match ($currentStatus) {
            EntryStatus::Published => "{$label} publikována",
            EntryStatus::Scheduled => 'Publikace naplánována',
            EntryStatus::InReview => 'Odesláno ke schválení',
            EntryStatus::Rejected => "{$label} zamítnuta",
            EntryStatus::Draft => in_array($previousStatus, [EntryStatus::Published, EntryStatus::Scheduled], true)
                ? 'Publikace zrušena'
                : 'Uloženo jako koncept',
        };
    }

    protected static function getPublicationNotificationBody(Model $record): ?string
    {
        return match ($record->status) {
            EntryStatus::Scheduled => 'Publikace je naplánována na '.(($record->scheduled_at ?? $record->published_at)?->format('j. n. Y H:i') ?? '—').'.',
            default => null,
        };
    }
}
