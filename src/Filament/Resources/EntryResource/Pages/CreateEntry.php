<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index', [
            'collection' => request()->query('collection'),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $collection = Collection::where('handle', request()->query('collection'))->first();

        if ($collection && empty($data['collection_id'])) {
            $data['collection_id'] = $collection->id;
        }

        if ($collection?->blueprint_id) {
            $data['blueprint_id'] = $collection->blueprint_id;
        }

        return $data;
    }

    public function getTitle(): string
    {
        $collection = EntryResource::getCurrentCollection();

        return $collection
            ? 'Nová položka — '.$collection->name
            : 'Nová položka';
    }
}
