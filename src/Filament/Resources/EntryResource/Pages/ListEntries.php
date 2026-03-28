<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\EntryResource;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn () => static::$resource::getUrl('create', [
                    'collection' => request()->query('collection'),
                ])),
        ];
    }

    public function getTitle(): string
    {
        $collection = EntryResource::getCurrentCollection();

        return $collection ? $collection->name : 'Položky';
    }
}
