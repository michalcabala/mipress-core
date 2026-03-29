<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Livewire\Attributes\Url;
use MiPress\Core\Filament\Resources\EntryResource;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    #[Url(as: 'collection')]
    public string $collectionHandle = '';

    public function table(Table $table): Table
    {
        return parent::table($table);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn () => static::$resource::getUrl('create', [
                    'collection' => $this->collectionHandle ?: null,
                ])),
        ];
    }

    public function getTitle(): string
    {
        return EntryResource::getCurrentCollection()?->name ?? 'Položky';
    }
}
