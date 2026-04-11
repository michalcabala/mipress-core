<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\EntryTaxonomySyncService;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    public string $collectionHandle = '';

    public function mount(?string $collection = null): void
    {
        if (blank($this->collectionHandle)) {
            $this->collectionHandle = $collection ?: (string) request()->query('collection', '');
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label('Uložit')
                ->icon('far-floppy-disk')
                ->formId('form'),
            $this->getCancelFormAction()
                ->icon('far-xmark'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index', [
            'collection' => $this->collectionHandle,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $collection = EntryResource::resolveCollectionByHandle($this->collectionHandle);

        if ($collection && empty($data['collection_id'])) {
            $data['collection_id'] = $collection->id;
        }

        if ($collection) {
            $data['blueprint_id'] = $collection->blueprint_id;
        }

        $data['parent_id'] = $this->resolveParentId($data, $collection);

        return app(WorkflowTransitionService::class)->prepareFormDataForStatus(
            $data,
            canPublish: (bool) auth()->user()?->hasPermissionTo('entry.publish'),
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
        $collection = EntryResource::resolveCollectionByHandle($this->collectionHandle);

        return $collection
            ? 'Nová položka — '.$collection->name
            : 'Nová položka';
    }

    protected function afterCreate(): void
    {
        $this->syncTaxonomyTerms();

        $record = $this->record;

        if (! $record instanceof Entry || $record->status !== EntryStatus::InReview) {
            return;
        }

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: 'Nový obsah ke schválení',
            body: 'Položka "'.$record->title.'" čeká na schválení publikace.',
            editUrl: EntryResource::getUrl('edit', [
                'record' => $record,
                'collection' => $record->collection?->handle,
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
}
