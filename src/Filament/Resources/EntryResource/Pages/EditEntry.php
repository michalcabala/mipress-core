<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Carbon\CarbonInterface;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;

class EditEntry extends EditRecord
{
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, HasWorkflowActions, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }

    protected static string $resource = EntryResource::class;

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
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
            $data['status'] = EntryStatus::InReview;
            $data['review_note'] = null;
        }

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

        $resolvedParentId = (int) $resolvedParentId;

        if ($resolvedParentId === (int) $record->getKey()) {
            return null;
        }

        if ($this->wouldCreateHierarchyCycle($record, $resolvedParentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Nelze vybrat podřízenou položku jako nadřazenou.',
            ]);
        }

        return $resolvedParentId;
    }

    private function wouldCreateHierarchyCycle(Entry $record, int $candidateParentId): bool
    {
        $currentId = $candidateParentId;
        $visited = [];

        while ($currentId > 0) {
            if ($currentId === (int) $record->getKey()) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;

            $parentId = Entry::query()
                ->where('collection_id', $record->collection_id)
                ->whereKey($currentId)
                ->value('parent_id');

            if (! is_numeric($parentId)) {
                return false;
            }

            $currentId = (int) $parentId;
        }

        return false;
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
