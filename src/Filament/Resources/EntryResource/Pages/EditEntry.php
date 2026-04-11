<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\EntryTaxonomySyncService;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowTransitionService;

class EditEntry extends EditRecord
{
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, HasWorkflowActions, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }
    use UsesCurrentPageSubNavigation;

    protected static string $resource = EntryResource::class;

    protected static ?string $navigationLabel = 'Editace';

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Zrušit')
            ->color('gray')
            ->url($this->getRedirectUrl());
    }

    protected function getRedirectUrl(): string
    {
        $collection = $this->getRecord()->collection;

        return static::$resource::getUrl('index', [
            'collection' => $collection?->handle,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if (
            $record instanceof Entry
            && $user?->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && in_array($record->status, [EntryStatus::Published, EntryStatus::InReview, EntryStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        $data['parent_id'] = $this->resolveParentId($data);

        if ($this->workflowIntent === 'review') {
            $data = app(WorkflowTransitionService::class)->prepareReviewData($data);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry) {
            return;
        }

        app(EntryTaxonomySyncService::class)->syncFromFormState($record, $this->form->getRawState());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data): ?int
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry || ! $record->collection?->hierarchical) {
            return null;
        }

        return app(HierarchyParentResolver::class)->resolveEntryParentForEdit(
            $record,
            data_get($data, 'parent_id'),
        );
    }

    protected function workflowRecordClass(): string
    {
        return Entry::class;
    }

    protected function workflowPublishActionName(): string
    {
        return 'publishEntry';
    }

    protected function workflowRejectActionName(): string
    {
        return 'rejectEntry';
    }

    protected function workflowUpdateActionName(): string
    {
        return 'updateEntry';
    }

    protected function workflowPublishedNotificationTitle(): string
    {
        return 'Položka publikována';
    }

    protected function workflowRejectedNotificationTitle(): string
    {
        return 'Položka zamítnuta';
    }

    protected function workflowScheduledNotificationBody(CarbonInterface $scheduleAt): string
    {
        return 'Záznam bude automaticky publikován '.$scheduleAt->format('j. n. Y H:i').'.';
    }

    protected function workflowReviewNotificationTitle(): string
    {
        return 'Nový obsah ke schválení';
    }

    protected function workflowReviewNotificationBody(Model $record): string
    {
        if (! $record instanceof Entry) {
            return 'Položka čeká na schválení publikace.';
        }

        return 'Položka "'.$record->title.'" čeká na schválení publikace.';
    }

    protected function workflowPreviewRouteName(): string
    {
        return 'preview.entry';
    }

    protected function workflowPreviewRouteParameterName(): string
    {
        return 'entry';
    }

    protected function workflowEditUrl(Model $record): string
    {
        if (! $record instanceof Entry) {
            return EntryResource::getUrl('index');
        }

        return EntryResource::getUrl('edit', [
            'record' => $record,
            'collection' => $record->collection?->handle,
        ]);
    }
}
