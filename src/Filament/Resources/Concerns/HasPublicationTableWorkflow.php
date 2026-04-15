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
use MiPress\Core\Enums\ContentStatus;
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
            ->label(__('mipress::admin.publication_workflow.view_live'))
            ->icon('far-arrow-up-right-from-square')
            ->color('gray')
            ->url(fn (Model $record): ?string => $record->getPublicUrl(), shouldOpenInNewTab: true)
            ->visible(fn (Model $record): bool => $record instanceof $modelClass
                && auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status === ContentStatus::Published
                && filled($record->getPublicUrl()));
    }

    protected static function makePreviewAction(): Action
    {
        $modelClass = static::getContentModelClass();

        return Action::make('preview')
            ->label(__('mipress::admin.publication_workflow.preview'))
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
                && $record->status !== ContentStatus::Published);
    }

    protected static function makeTogglePublicationAction(): Action
    {
        $modelClass = static::getContentModelClass();

        return static::refreshesPublicationStatusOverview(
            Action::make('togglePublicationStatus')
                ->label(__('mipress::admin.publication_workflow.change_publication'))
                ->icon('far-arrows-rotate')
                ->color('gray')
                ->visible(fn (Model $record): bool => $record instanceof $modelClass
                    && auth()->user()?->can('publish', $record) === true
                    && ! $record->trashed())
                ->modalHeading(fn (Model $record): string => __('mipress::admin.publication_workflow.modal_heading', ['title' => $record->title]))
                ->modalSubmitActionLabel(__('mipress::admin.publication_workflow.save_changes'))
                ->fillForm(fn (Model $record): array => [
                    'status' => $record->status->value,
                    'published_at' => $record->scheduled_at ?? $record->published_at,
                ])
                ->schema(fn (Model $record): array => static::getPublicationWorkflowSchema($record))
                ->action(function (Model $record, array $data): void {
                    $previousStatus = $record->status;

                    if (! static::applyPublicationWorkflowData($record, $data)) {
                        Notification::make()
                            ->title(__('mipress::admin.publication_workflow.no_change'))
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
                })
        );
    }

    protected static function makeBulkPublicationAction(): BulkAction
    {
        $modelClass = static::getContentModelClass();
        $label = static::getContentLabelPlural();

        return static::refreshesPublicationStatusOverviewBulkAction(
            BulkAction::make('changePublicationStatus')
                ->label(__('mipress::admin.publication_workflow.change_publication'))
                ->icon('far-arrows-rotate')
                ->visible(fn (): bool => auth()->user()?->hasPermissionTo(static::getPublishPermission()) === true)
                ->modalHeading(__('mipress::admin.publication_workflow.bulk_modal_heading', ['label' => $label]))
                ->modalSubmitActionLabel(__('mipress::admin.publication_workflow.save_changes'))
                ->schema(static::getPublicationWorkflowSchema())
                ->action(function (EloquentCollection $records, array $data) use ($modelClass): void {
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
                        ->title($updated > 0 ? __('mipress::admin.publication_workflow.changed_title') : __('mipress::admin.publication_workflow.no_change'))
                        ->body(__('mipress::admin.publication_workflow.bulk_result', ['updated' => $updated, 'skipped' => $skipped]))
                        ->{$updated > 0 ? 'success' : 'warning'}()
                        ->send();
                })
        );
    }

    protected static function refreshesPublicationStatusOverview(Action $action): Action
    {
        return $action->after(fn ($livewire) => static::dispatchPublicationStatusOverviewRefresh($livewire));
    }

    protected static function refreshesPublicationStatusOverviewBulkAction(BulkAction $action): BulkAction
    {
        return $action->after(fn ($livewire) => static::dispatchPublicationStatusOverviewRefresh($livewire));
    }

    protected static function dispatchPublicationStatusOverviewRefresh(mixed $livewire): void
    {
        if (is_object($livewire) && method_exists($livewire, 'dispatch')) {
            $livewire->dispatch(static::getPublicationStatusOverviewRefreshEventName());
        }
    }

    protected static function getPublicationStatusOverviewRefreshEventName(): string
    {
        return 'entry-publication-status-updated';
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
                ->label(__('mipress::admin.publication_workflow.publication_state'))
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
                ->label(__('mipress::admin.publication_workflow.publication_date'))
                ->nullable()
                ->disabled(fn (): bool => ! static::canPublishRecord($record))
                ->helperText(__('mipress::admin.publication_workflow.publication_date_helper')),
            static::canPublishRecord($record),
        );
    }

    /**
     * @return array<string, string>
     */
    protected static function getPublicationStatusOptions(?Model $record): array
    {
        return collect(static::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, ContentStatus>
     */
    protected static function getVisiblePublicationStatuses(?Model $record): array
    {
        $modelClass = static::getContentModelClass();

        if (static::canPublishRecord($record)) {
            return ContentStatus::cases();
        }

        if (! $record instanceof $modelClass) {
            return [ContentStatus::Draft, ContentStatus::InReview];
        }

        return match ($record->status) {
            ContentStatus::Published, ContentStatus::Scheduled => [$record->status, ContentStatus::InReview],
            ContentStatus::Rejected => [$record->status, ContentStatus::Draft, ContentStatus::InReview],
            default => [ContentStatus::Draft, ContentStatus::InReview],
        };
    }

    /**
     * @return array<string, string|array|null>
     */
    protected static function getPublicationStatusColors(): array
    {
        return collect(ContentStatus::cases())
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getColor()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    protected static function getPublicationStatusIcons(): array
    {
        return collect(ContentStatus::cases())
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getIcon()])
            ->all();
    }

    protected static function publicationStatusHelperText(?Model $record): string
    {
        $modelClass = static::getContentModelClass();

        if (static::canPublishRecord($record)) {
            return __('mipress::admin.entry_like_form.publication_helper.can_publish');
        }

        if ($record instanceof $modelClass && in_array($record->status, [ContentStatus::Published, ContentStatus::Scheduled], true)) {
            return __('mipress::admin.entry_like_form.publication_helper.needs_review_after_publish');
        }

        return __('mipress::admin.entry_like_form.publication_helper.choose_state');
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
        $nextStatus = $nextStatus instanceof ContentStatus
            ? $nextStatus
            : ContentStatus::tryFrom((string) $nextStatus);

        if (! $nextStatus instanceof ContentStatus) {
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

    protected static function sendReviewRequestedNotificationIfNeeded(Model $record, ContentStatus $previousStatus): void
    {
        if ($previousStatus === $record->status || $record->status !== ContentStatus::InReview) {
            return;
        }

        $contentLabel = static::getContentLabel();

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: static::getPublishPermission(),
            title: __('mipress::admin.publication_workflow.review_request_title'),
            body: __('mipress::admin.publication_workflow.review_request_body', ['label' => $contentLabel, 'title' => $record->title]),
            editUrl: static::getEditUrl($record),
            previewRouteName: static::getPreviewRouteName(),
            previewRouteParameterName: static::getPreviewRouteParameterName(),
        );
    }

    protected static function getPublicationNotificationTitle(ContentStatus $previousStatus, ContentStatus $currentStatus): string
    {
        $label = static::getContentLabel();

        return match ($currentStatus) {
            ContentStatus::Published => __('mipress::admin.publication_workflow.status_changed.published', ['label' => $label]),
            ContentStatus::Scheduled => __('mipress::admin.publication_workflow.status_changed.scheduled'),
            ContentStatus::InReview => __('mipress::admin.publication_workflow.status_changed.in_review'),
            ContentStatus::Rejected => __('mipress::admin.publication_workflow.status_changed.rejected', ['label' => $label]),
            ContentStatus::Draft => in_array($previousStatus, [ContentStatus::Published, ContentStatus::Scheduled], true)
                ? __('mipress::admin.publication_workflow.status_changed.unpublished')
                : __('mipress::admin.publication_workflow.status_changed.saved_draft'),
        };
    }

    protected static function getPublicationNotificationBody(Model $record): ?string
    {
        return match ($record->status) {
            ContentStatus::Scheduled => __('mipress::admin.publication_workflow.scheduled_body', ['date' => ($record->scheduled_at ?? $record->published_at)?->format('j. n. Y H:i') ?? '—']),
            default => null,
        };
    }
}
