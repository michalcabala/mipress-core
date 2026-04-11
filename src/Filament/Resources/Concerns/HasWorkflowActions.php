<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\AuditLog;

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
                ->label('Další akce')
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
            EntryStatus::Draft => $isContributor
                ? $this->makeSubmitForReviewAction('Odeslat ke schválení')
                : ($canPublish ? $this->makePublishAction('Publikovat') : null),
            EntryStatus::InReview => $canPublish
                ? $this->makePublishAction('Schválit a publikovat')
                : null,
            EntryStatus::Published, EntryStatus::Scheduled => $isContributor && $isOwner
                ? $this->makeSubmitForReviewAction('Odeslat změny ke schválení')
                : $this->makeUpdateAction(),
            EntryStatus::Rejected => $isContributor && $isOwner
                ? $this->makeResubmitRejectedAction()
                : ($canPublish ? $this->makePublishAction('Publikovat') : null),
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

        if ($record->status === EntryStatus::Draft) {
            $actions[] = $this->makeSaveDraftAction();
        }

        if ($record->status === EntryStatus::InReview && $canPublish) {
            $actions[] = $this->makeRejectAction();
            $actions[] = $this->makeReturnToDraftAction('Uložit koncept');
        }

        if ($record->status === EntryStatus::Published && $canPublish) {
            $actions[] = $this->makeUnpublishAction();
        }

        if ($record->status === EntryStatus::Scheduled) {
            $actions[] = $this->makeCancelScheduleAction();

            if ($canPublish) {
                $actions[] = $this->makePublishNowAction();
            }
        }

        if ($record->status === EntryStatus::Rejected) {
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

        if ($record->status === EntryStatus::Published && filled($record->getPublicUrl())) {
            return Action::make('viewLive')
                ->label('Zobrazit na webu')
                ->icon('far-arrow-up-right-from-square')
                ->color('gray')
                ->url($record->getPublicUrl(), shouldOpenInNewTab: true);
        }

        return Action::make('preview')
            ->label('Náhled')
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
            ->label('Aktualizovat')
            ->color('primary')
            ->icon('far-floppy-disk')
            ->action(fn () => $this->save());
    }

    private function makeSaveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label('Uložit koncept')
            ->icon(EntryStatus::Draft->getIcon())
            ->color(EntryStatus::Draft->getColor())
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $oldStatus = $record->status;
                $record->status = EntryStatus::Draft;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);

                Notification::make()
                    ->title('Koncept uložen')
                    ->success()
                    ->send();
            });
    }

    private function makeSubmitForReviewAction(string $label): Action
    {
        return Action::make('submitForReview')
            ->label($label)
            ->icon(EntryStatus::InReview->getIcon())
            ->color(EntryStatus::InReview->getColor())
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

                AuditLog::logStatusChange($record, EntryStatus::InReview, $oldStatus);

                $this->sendReviewRequestedDatabaseNotifications($record);

                Notification::make()
                    ->title('Odesláno ke schválení')
                    ->success()
                    ->send();
            });
    }

    private function makePublishAction(string $label): Action
    {
        return Action::make($this->workflowPublishActionName())
            ->label($label)
            ->icon(EntryStatus::Published->getIcon())
            ->color(EntryStatus::Published->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $oldStatus = $record->status;

                $scheduleAt = $record->scheduled_at ?? $record->published_at;

                if ($scheduleAt?->isFuture()) {
                    $record->status = EntryStatus::Scheduled;
                    $record->scheduled_at = $scheduleAt;
                    $record->published_at = $scheduleAt;
                    $record->review_note = null;
                    $record->save();

                    AuditLog::logStatusChange($record, EntryStatus::Scheduled, $oldStatus);

                    Notification::make()
                        ->title('Publikace naplánována')
                        ->body($this->workflowScheduledNotificationBody($scheduleAt))
                        ->success()
                        ->send();

                    $this->releaseLockAndRedirect();

                    return;
                }

                $record->status = EntryStatus::Published;
                $record->published_at ??= now();
                $record->scheduled_at = null;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);

                Notification::make()
                    ->title($this->workflowPublishedNotificationTitle())
                    ->success()
                    ->send();

                $this->releaseLockAndRedirect();
            });
    }

    private function releaseLockAndRedirect(): void
    {
        $record = $this->getWorkflowRecord();

        if ($record instanceof Model) {
            $record->unlock();
        }

        $redirectUrl = $this->getRedirectUrl();

        $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
    }

    private function makeRejectAction(): Action
    {
        return Action::make($this->workflowRejectActionName())
            ->label('Zamítnout')
            ->icon(EntryStatus::Rejected->getIcon())
            ->color(EntryStatus::Rejected->getColor())
            ->schema([
                Textarea::make('reason')
                    ->label('Důvod zamítnutí')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Rejected;
                $record->review_note = $data['reason'];
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Rejected, $oldStatus, $data['reason']);

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
            ->icon(EntryStatus::Draft->getIcon())
            ->color(EntryStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Draft;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);

                Notification::make()
                    ->title('Vráceno do konceptu')
                    ->success()
                    ->send();
            });
    }

    private function makeUnpublishAction(): Action
    {
        return Action::make('unpublish')
            ->label('Zrušit publikaci')
            ->icon(EntryStatus::Draft->getIcon())
            ->color(EntryStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model || auth()->user()?->can('publish', $record) !== true) {
                    abort(403);
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Draft;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);

                Notification::make()
                    ->title('Publikace zrušena')
                    ->success()
                    ->send();
            });
    }

    private function makeCancelScheduleAction(): Action
    {
        return Action::make('cancelSchedule')
            ->label('Zrušit plánování')
            ->icon(EntryStatus::Draft->getIcon())
            ->color(EntryStatus::Draft->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Draft;
                $record->review_note = null;
                $record->published_at = null;
                $record->scheduled_at = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);

                Notification::make()
                    ->title('Plánování zrušeno')
                    ->success()
                    ->send();
            });
    }

    private function makePublishNowAction(): Action
    {
        return Action::make('publishNow')
            ->label('Publikovat ihned')
            ->icon(EntryStatus::Published->getIcon())
            ->color(EntryStatus::Published->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model || auth()->user()?->can('publish', $record) !== true) {
                    abort(403);
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Published;
                $record->published_at = now();
                $record->scheduled_at = null;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);

                Notification::make()
                    ->title($this->workflowPublishedNotificationTitle())
                    ->success()
                    ->send();
            });
    }

    private function makeResubmitRejectedAction(): Action
    {
        return Action::make('resubmitRejected')
            ->label('Upravit a znovu odeslat')
            ->icon(EntryStatus::InReview->getIcon())
            ->color(EntryStatus::InReview->getColor())
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getWorkflowRecord();

                if (! $record instanceof Model) {
                    return;
                }

                $record->refresh();
                $oldStatus = $record->status;
                $record->status = EntryStatus::InReview;
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::InReview, $oldStatus);

                $this->sendReviewRequestedDatabaseNotifications($record);

                Notification::make()
                    ->title('Odesláno ke schválení')
                    ->success()
                    ->send();
            });
    }

    private function sendReviewRequestedDatabaseNotifications(Model $record): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approvers = User::query()
            ->permission($this->workflowReviewPermission())
            ->whereKeyNot(auth()->id())
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($this->workflowReviewNotificationTitle())
            ->body($this->workflowReviewNotificationBody($record))
            ->warning()
            ->actions([
                Action::make('approve')
                    ->label('Schválit')
                    ->button()
                    ->color('success')
                    ->url(
                        $this->workflowEditUrl($record),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
                Action::make('view')
                    ->label('Zobrazit')
                    ->button()
                    ->color('gray')
                    ->url(
                        URL::temporarySignedRoute(
                            $this->workflowPreviewRouteName(),
                            now()->addHour(),
                            [$this->workflowPreviewRouteParameterName() => $record->getKey()],
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
                Action::make('edit')
                    ->label('Upravit')
                    ->button()
                    ->color('primary')
                    ->url(
                        $this->workflowEditUrl($record),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
            ])
            ->sendToDatabase($approvers);
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
