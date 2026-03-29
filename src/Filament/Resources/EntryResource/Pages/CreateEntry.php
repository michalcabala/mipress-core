<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    #[Url(as: 'collection')]
    public string $collectionHandle = '';

    public function mount(): void
    {
        parent::mount();

        $this->collectionHandle = request()->query('collection', '');
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index', [
            'collection' => $this->collectionHandle,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $collection = filled($this->collectionHandle)
            ? Collection::where('handle', $this->collectionHandle)->first()
            : null;

        if ($collection && empty($data['collection_id'])) {
            $data['collection_id'] = $collection->id;
        }

        if ($collection?->blueprint_id) {
            $data['blueprint_id'] = $collection->blueprint_id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        $collection = filled($this->collectionHandle)
            ? Collection::where('handle', $this->collectionHandle)->first()
            : null;

        return $collection
            ? 'Nová položka — '.$collection->name
            : 'Nová položka';
    }
}
