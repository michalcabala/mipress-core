<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
    }

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

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        $collection = $this->getRecord()->collection;

        return static::$resource::getUrl('index', [
            'collection' => $collection?->handle,
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['parent_id'] = $this->resolveParentId($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncTaxonomyTerms();
    }

    private function syncTaxonomyTerms(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry) {
            return;
        }

        $formState = $this->form->getRawState();

        $incomingTermIds = collect($formState)
            ->filter(fn ($value, string $key): bool => str_starts_with($key, 'taxonomy__'))
            ->flatten()
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        // Obtain taxonomy IDs from the form keys to scope the sync
        $taxonomyIds = collect($formState)
            ->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'taxonomy__'))
            ->map(fn (string $key): int => (int) str_replace('taxonomy__', '', $key))
            ->values()
            ->all();

        // Remove old terms that belong to the collection's taxonomies
        if (! empty($taxonomyIds)) {
            $record->terms()->wherePivot('term_id', '!=', 0)
                ->whereIn('taxonomy_id', $taxonomyIds)
                ->detach();
        }

        if (! empty($incomingTermIds)) {
            $record->terms()->attach($incomingTermIds);
        }
    }

    private function getPrimaryWorkflowAction(): ?Action
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (! $record instanceof Entry || $user === null) {
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
            EntryStatus::Published, EntryStatus::Scheduled => $this->makeUpdateAction(),
            EntryStatus::Rejected => $isContributor && $isOwner
                ? $this->makeResubmitRejectedAction()
                : ($canPublish ? $this->makePublishAction('Publikovat') : null),
        };
    }

    private function resolveParentId(array $data): ?int
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry || ! $record->collection?->hierarchical) {
            return null;
        }

        $parentId = data_get($data, 'parent_id');

        if (! is_numeric($parentId)) {
            return null;
        }

        $resolvedParentId = Entry::query()
            ->where('collection_id', $record->collection_id)
            ->whereKey((int) $parentId)
            ->value('id');

        if (! is_numeric($resolvedParentId)) {
            return null;
        }

        return (int) $resolvedParentId === (int) $record->getKey()
            ? null
            : (int) $resolvedParentId;
    }

    /**
     * @return array<int, Action>
     */
    private function getSecondaryWorkflowActions(): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (! $record instanceof Entry || $user === null) {
            return [];
        }

        $canPublish = $user->can('publish', $record);
        $isContributor = $user->hasRole('contributor');
        $isOwner = (int) $record->author_id === (int) $user->getKey();

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

            if (! $isContributor && $canPublish) {
                // Primary action is publish.
            } elseif ($isContributor && $isOwner) {
                // Primary action is resubmit.
            }
        }

        return $actions;
    }

    private function getPreviewOrLiveAction(): ?Action
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry) {
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
                URL::temporarySignedRoute('preview.entry', now()->addHour(), ['entry' => $record->getKey()]),
                shouldOpenInNewTab: true,
            );
    }

    private function makeUpdateAction(): Action
    {
        return Action::make('updateEntry')
            ->label('Aktualizovat')
            ->color('primary')
            ->icon('far-floppy-disk')
            ->action(fn () => $this->save());
    }

    private function makeSaveDraftAction(): Action
    {
        return Action::make('saveDraft')
            ->label('Uložit koncept')
            ->icon('far-floppy-disk')
            ->color('gray')
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getRecord();

                if (! $record instanceof Entry) {
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
            ->icon('far-paper-plane')
            ->color('primary')
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getRecord();

                if (! $record instanceof Entry) {
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

    private function makePublishAction(string $label): Action
    {
        return Action::make('publishEntry')
            ->label($label)
            ->icon('far-circle-check')
            ->color('primary')
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getRecord();

                if (! $record instanceof Entry) {
                    return;
                }

                $record->refresh();
                $oldStatus = $record->status;

                if ($record->published_at?->isFuture()) {
                    $record->status = EntryStatus::Scheduled;
                    $record->review_note = null;
                    $record->save();

                    AuditLog::logStatusChange($record, EntryStatus::Scheduled, $oldStatus);

                    Notification::make()
                        ->title('Publikace naplánována')
                        ->body('Záznam bude automaticky publikován '.$record->published_at->format('j. n. Y H:i').'.')
                        ->success()
                        ->send();

                    return;
                }

                $record->status = EntryStatus::Published;
                $record->published_at ??= now();
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);

                Notification::make()
                    ->title('Položka publikována')
                    ->success()
                    ->send();
            });
    }

    private function makeRejectAction(): Action
    {
        return Action::make('rejectEntry')
            ->label('Zamítnout')
            ->icon('far-circle-xmark')
            ->color('danger')
            ->schema([
                \Filament\Forms\Components\Textarea::make('reason')
                    ->label('Důvod zamítnutí')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                $record = $this->getRecord();

                if (! $record instanceof Entry) {
                    return;
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Rejected;
                $record->review_note = $data['reason'];
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Rejected, $oldStatus, $data['reason']);

                Notification::make()
                    ->title('Položka zamítnuta')
                    ->warning()
                    ->send();
            });
    }

    private function makeReturnToDraftAction(string $label): Action
    {
        return Action::make('returnToDraft')
            ->label($label)
            ->icon('far-rotate-left')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getRecord();

                if (! $record instanceof Entry) {
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
            ->icon('far-eye-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getRecord();

                if (! $record instanceof Entry || auth()->user()?->can('publish', $record) !== true) {
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
            ->icon('far-calendar-xmark')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getRecord();

                if (! $record instanceof Entry) {
                    return;
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Draft;
                $record->review_note = null;
                $record->published_at = null;
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
            ->icon('far-bolt')
            ->color('primary')
            ->requiresConfirmation()
            ->action(function (): void {
                $record = $this->getRecord();

                if (! $record instanceof Entry || auth()->user()?->can('publish', $record) !== true) {
                    abort(403);
                }

                $oldStatus = $record->status;
                $record->status = EntryStatus::Published;
                $record->published_at = now();
                $record->review_note = null;
                $record->save();

                AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);

                Notification::make()
                    ->title('Položka publikována')
                    ->success()
                    ->send();
            });
    }

    private function makeResubmitRejectedAction(): Action
    {
        return Action::make('resubmitRejected')
            ->label('Upravit a znovu odeslat')
            ->icon('far-paper-plane')
            ->color('primary')
            ->requiresConfirmation()
            ->action(function (): void {
                $this->save(false, false);

                $record = $this->getRecord();

                if (! $record instanceof Entry) {
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

    private function sendReviewRequestedDatabaseNotifications(Entry $record): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approvers = User::query()
            ->permission('entry.publish')
            ->whereKeyNot(auth()->id())
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Nový obsah ke schválení')
            ->body('Položka "'.$record->title.'" čeká na schválení publikace.')
            ->warning()
            ->sendToDatabase($approvers);
    }
}
