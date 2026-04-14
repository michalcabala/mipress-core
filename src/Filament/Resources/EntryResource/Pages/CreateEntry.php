<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\Concerns\HasCreateWorkflowActions;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\EntryTaxonomySyncService;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class CreateEntry extends CreateRecord
{
    use HasContextualCrudNotifications;
    use HasCreateWorkflowActions;

    protected static string $resource = EntryResource::class;

    public string $collectionHandle = '';

    public function mount(?string $collection = null): void
    {
        if (blank($this->collectionHandle)) {
            $this->collectionHandle = $collection ?: (string) request()->query('collection', '');
        }

        $this->ensureAccessibleCollectionHandle();

        parent::mount();
    }



    protected function getRedirectUrl(): string
    {
        $this->ensureAccessibleCollectionHandle();

        return static::$resource::getUrl('index', static::$resource::collectionUrlParameters($this->collectionHandle));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->ensureAccessibleCollectionHandle();

        $collection = EntryResource::resolveCollectionByHandle($this->collectionHandle);

        if ($collection && empty($data['collection_id'])) {
            $data['collection_id'] = $collection->id;
        }

        if ($collection) {
            $data['blueprint_id'] = $collection->blueprint_id;
        }

        $data['parent_id'] = $this->resolveParentId($data, $collection);

        return app(WorkflowTransitionService::class)->prepareCreateDataForIntent(
            $data,
            $this->workflowCreateIntent(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data, ?Collection $collection): ?int
    {
        return app(HierarchyParentResolver::class)->resolveEntryParentForCreate(
            $collection,
            data_get($data, 'parent_id'),
        );
    }

    public function getTitle(): string
    {
        $this->ensureAccessibleCollectionHandle();

        $collection = EntryResource::resolveCollectionByHandle($this->collectionHandle);

        return $collection
            ? 'Nová položka — '.$collection->name
            : 'Nová položka';
    }

    protected function afterCreate(): void
    {
        $this->syncTaxonomyTerms();

        $record = $this->record;

        if (! $record instanceof Entry || $record->status !== ContentStatus::InReview) {
            return;
        }

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: 'Nový obsah ke schválení',
            body: 'Položka "'.$record->title.'" čeká na schválení publikace.',
                editUrl: EntryResource::getUrl('edit', [
                'record' => $record,
                    ...EntryResource::collectionUrlParameters($record->collection?->handle),
                ]),
            previewRouteName: 'preview.entry',
            previewRouteParameterName: 'entry',
        );
    }

    private function syncTaxonomyTerms(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry) {
            return;
        }

        app(EntryTaxonomySyncService::class)->syncFromFormState($record, $this->form->getRawState());
    }

    private function ensureAccessibleCollectionHandle(): void
    {
        $this->collectionHandle = EntryResource::resolveAccessibleCollectionHandle($this->collectionHandle);
    }
}
