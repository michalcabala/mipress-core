<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    #[Url(as: 'collection')]
    public string $collectionHandle = '';

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(function (Builder $query): void {
                if (blank($this->collectionHandle)) {
                    return;
                }

                $collection = Collection::where('handle', $this->collectionHandle)->first();

                if ($collection) {
                    $query->where('collection_id', $collection->id);
                }
            });
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
