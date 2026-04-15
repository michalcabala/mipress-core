<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

trait HasWorkflowActions
{
    protected ?string $workflowIntent = null;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($previewAction = $this->getPreviewOrLiveAction()) {
            $actions[] = $previewAction;
        }

        if ($primaryAction = $this->getPrimaryWorkflowAction()) {
            $actions[] = $primaryAction;
        }

        $secondaryActions = $this->getSecondaryWorkflowActions();

        if ($secondaryActions !== []) {
            $actions[] = ActionGroup::make($secondaryActions)
                ->label(__('mipress::admin.workflow_actions.more_actions'))
                ->icon('far-ellipsis')
                ->color('gray')
                ->button();
        }

        $actions[] = $this->getCancelFormAction();

        return $actions;
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function workflowRecordClass(): string;

    abstract protected function workflowPublishActionName(): string;

    abstract protected function workflowRejectActionName(): string;

    abstract protected function workflowUpdateActionName(): string;

    abstract protected function workflowPublishedNotificationTitle(): string;

    abstract protected function workflowRejectedNotificationTitle(): string;

    abstract protected function workflowScheduledNotificationBody(CarbonInterface $scheduleAt): string;

    abstract protected function workflowReviewNotificationTitle(): string;

    abstract protected function workflowReviewNotificationBody(Model $record): string;

    abstract protected function workflowPreviewRouteName(): string;

    abstract protected function workflowPreviewRouteParameterName(): string;

    abstract protected function workflowEditUrl(Model $record): string;

    abstract protected function workflowCompletedRedirectUrl(): string;

    abstract protected function getCancelFormAction(): Action;

    protected function workflowReviewPermission(): string
    {
        return 'entry.publish';
    }

    private function getPrimaryWorkflowAction(): ?Action
    {
        $record = $this->getWorkflowRecord();
        $user = auth()->user();

        if (! $record instanceof Model || $user === null) {
            return null;
        }

        $canPublish = $user->can('publish', $record);
        $isContributor = $user->hasRole('contributor');
        $isOwner = (int) $record->author_id === (int) $user->getKey();

        return match ($record->status) {
            ContentStatus::Draft => $isContributor
                ? $this->makeSubmitForReviewAction(__('mipress::admin.workflow_actions.primary.submit_for_review'))
                : ($canPublish ? $this->makePublishAction(__('mipress::admin.workflow_actions.primary.publish')) : null),
            ContentStatus::InReview => $canPublish
                ? $this->makePublishAction(__('mipress::admin.workflow_actions.primary.approve_and_publish'))
                : null,
            ContentStatus::Published, ContentStatus::Scheduled => $isContributor && $isOwner
                ? $this->makeSubmitForReviewAction(__('mipress::admin.workflow_actions.primary.submit_changes_for_review'))
                : $this->makeUpdateAction(),
            ContentStatus::Rejected => $isContributor && $isOwner
                ? $this->makeResubmitRejectedAction()
                : ($canPublish ? $this->makePublishAction(__('mipress::admin.workflow_actions.primary.publish')) : null),
        };
    }

    /**
     * @return array<int, Action>
     */
    private function getSecondaryWorkflowActions(): array
    {
        $record = $this->getWorkflowRecord();
        $user = auth()->user();

        if (! $record instanceof Model || $user === null) {
            return [];
        }

        $canPublish = $user->can('publish', $record);

        $actions = [];

        if ($record->status === ContentStatus::Draft) {
            $actions[] = $this->makeSaveDraftAction();
        }

        if ($record->status === ContentStatus::InReview && $canPublish) {
            $actions[] = $this->makeRejectAction();
            $actions[] = $this->makeReturnToDraftAction(__('mipress::admin.workflow_actions.save_draft'));
        }

        if ($record->status === ContentStatus::Published && $canPublish) {
            $actions[] = $this->makeUnpublishAction();
        }

        if ($record->status === ContentStatus::Scheduled) {
            $actions[] = $this->makeCancelScheduleAction();

            if ($canPublish) {
                $actions[] = $this->makePublishNowAction();
            }
        }

        if ($record->status === ContentStatus::Rejected) {
            $actions[] = $this->makeSaveDraftAction();
        }

        return $actions;
    }

    private function getPreviewOrLiveAction(): ?Action
    {
        $record = $this->getWorkflowRecord();

        if (! $record instanceof Model) {
            return null;
        }

        if (auth()->user()?->can('view', $record) !== true) {
            return null;
        }

        if ($record->status === ContentStatus::Published && filled($record->getPublicUrl())) {
            return Action::make('viewLive')
                ->label(__('mipress::admin.workflow_actions.view_live'))
                ->icon('far-arrow-up-right-from-square')
                ->color('gray')
                ->url($record->getPublicUrl(), shouldOpenInNewTab: true);
        }

        return Action::make('preview')
            ->label(__('mipress::admin.workflow_actions.preview'))
            ->icon('far-eye')
            ->color('gray')
            ->url(
                URL::temporarySignedRoute(
                    $this->workflowPreviewRouteName(),
                    now()->addHour(),
                    [$this->workflowPreviewRouteParameterName() => $record->getKey()],
                ),
                shouldOpenInNewTab: true,
            );
    }

    private function makeUpdateAction(): Action
    {
        return Action::make($this->workflowUpdateActionName())
            ->label(__('mipress::admin.workflow_actions.update'))
            ->color('primary')
            ->icon('far-floppy-disk')
            ->action(fn () => $this->save());
    }

    private function makeSaveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label(__('mipress::admin.workflow_actions.save_draft'))
            ->icon(ContentStatus::Draft->getIcon())
            ->color(ContentStatus::Draft->getColor())
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $transition = $this->workflowTransitions()->saveDraft($record);

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.draft_saved'))
                    ->success()
                    ->send();
            });
    }

    private function makeSubmitForReviewAction(string $label): Action
    {
        return Action::make('submitForReview')
            ->label($label)
            ->icon(ContentStatus::InReview->getIcon())
            ->color(ContentStatus::InReview->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $oldStatus = $record->status;

                $this->workflowIntent = 'review';
                $this->save(false, false);
                $this->workflowIntent = null;

                $record->refresh();

                $this->workflowNotifications()->sendReviewRequestedDatabaseNotifications(
                    record: $record,
                    permission: $this->workflowReviewPermission(),
                    title: $this->workflowReviewNotificationTitle(),
                    body: $this->workflowReviewNotificationBody($record),
                    editUrl: $this->workflowEditUrl($record),
                    previewRouteName: $this->workflowPreviewRouteName(),
                    previewRouteParameterName: $this->workflowPreviewRouteParameterName(),
                );

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.review_sent'))
                    ->success()
                    ->send();
            });
    }

    private function makePublishAction(string $label): Action
    {
        return Action::make($this->workflowPublishActionName())
            ->label($label)
            ->icon(ContentStatus::Published->getIcon())
            ->color(ContentStatus::Published->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->workflowIntent = 'publish';
                $this->save(false, false);
                $this->workflowIntent = null;

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $transition = $this->workflowTransitions()->publish($record);

                if ($transition->isScheduled()) {
                    Notification::make()
                        ->title(__('mipress::admin.workflow_actions.scheduled_title'))
                        ->body($this->workflowScheduledNotificationBody($transition->scheduledFor ?? now()))
                        ->success()
                        ->send();

                    $this->releaseLockAndRedirect();

                    return;
                }

                Notification::make()
                    ->title($this->workflowPublishedNotificationTitle())
                    ->success()
                    ->send();

                $this->releaseLockAndRedirect();
            });
    }

    private function releaseLockAndRedirect(): void
    {
        $redirectUrl = $this->workflowCompletedRedirectUrl();

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }

    private function makeRejectAction(): Action
    {
        return Action::make($this->workflowRejectActionName())
            ->label(__('mipress::admin.workflow_actions.reject'))
            ->icon(ContentStatus::Rejected->getIcon())
            ->color(ContentStatus::Rejected->getColor())
            ->schema([
                Textarea::make('reason')
                    ->label(__('mipress::admin.workflow_actions.reject_reason'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $transition = $this->workflowTransitions()->reject($record, $data['reason']);

                Notification::make()
                    ->title($this->workflowRejectedNotificationTitle())
                    ->warning()
                    ->send();
            });
    }

    private function makeReturnToDraftAction(string $label): Action
    {
        return Action::make('returnToDraft')
            ->label($label)
            ->icon(ContentStatus::Draft->getIcon())
            ->color(ContentStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $transition = $this->workflowTransitions()->saveDraft($record);

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.returned_to_draft'))
                    ->success()
                    ->send();
            });
    }

    private function makeUnpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label(__('mipress::admin.workflow_actions.unpublish'))
            ->icon(ContentStatus::Draft->getIcon())
            ->color(ContentStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model || auth()->user()?->can('publish', $record) !== true) {
                    abort(403);
                }

                $transition = $this->workflowTransitions()->unpublish($record);

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.unpublished'))
                    ->success()
                    ->send();
            });
    }

    private function makeCancelScheduleAction(): Action
    {
        return Action::make('cancelSchedule')
            ->label(__('mipress::admin.workflow_actions.cancel_schedule'))
            ->icon(ContentStatus::Draft->getIcon())
            ->color(ContentStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $transition = $this->workflowTransitions()->cancelSchedule($record);

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.schedule_canceled'))
                    ->success()
                    ->send();
            });
    }

    private function makePublishNowAction(): Action
    {
        return Action::make('publishNow')
            ->label(__('mipress::admin.workflow_actions.publish_now'))
            ->icon(ContentStatus::Published->getIcon())
            ->color(ContentStatus::Published->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model || auth()->user()?->can('publish', $record) !== true) {
                    abort(403);
                }

                $transition = $this->workflowTransitions()->publishNow($record);

                Notification::make()
                    ->title($this->workflowPublishedNotificationTitle())
                    ->success()
                    ->send();
            });
    }

    private function makeResubmitRejectedAction(): Action
    {
        return Action::make('resubmitRejected')
            ->label(__('mipress::admin.workflow_actions.resubmit_rejected'))
            ->icon(ContentStatus::InReview->getIcon())
            ->color(ContentStatus::InReview->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $transition = $this->workflowTransitions()->transitionToReview($record);

                $this->workflowNotifications()->sendReviewRequestedDatabaseNotifications(
                    record: $record,
                    permission: $this->workflowReviewPermission(),
                    title: $this->workflowReviewNotificationTitle(),
                    body: $this->workflowReviewNotificationBody($record),
                    editUrl: $this->workflowEditUrl($record),
                    previewRouteName: $this->workflowPreviewRouteName(),
                    previewRouteParameterName: $this->workflowPreviewRouteParameterName(),
                );

                Notification::make()
                    ->title(__('mipress::admin.workflow_actions.review_sent'))
                    ->success()
                    ->send();
            });
    }

    private function workflowTransitions(): WorkflowTransitionService
    {
        return app(WorkflowTransitionService::class);
    }

    private function workflowNotifications(): WorkflowNotificationService
    {
        return app(WorkflowNotificationService::class);
    }

    private function getWorkflowRecord(): ?Model
    {
        $record = $this->getRecord();
        $recordClass = $this->workflowRecordClass();

        if (! $record instanceof $recordClass) {
            return null;
        }

        return $record;
    }
}
