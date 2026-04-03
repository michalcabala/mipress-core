<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Resources\EntryResource\Tables\EntriesTable;
use MiPress\Core\Models\Collection;

class ListEntries extends ListRecords
{
    protected static string $resource = EntryResource::class;

    public string $collectionHandle = '';

    private bool $hasResolvedCollection = false;

    private ?Collection $resolvedCollection = null;

    public function mount(?string $collection = null): void
    {
        if (blank($this->collectionHandle)) {
            $this->collectionHandle = $collection ?: (string) request()->query('collection', '');

            if (blank($this->collectionHandle)) {
                $this->collectionHandle = (string) Collection::query()
                    ->where('handle', '!=', 'pages')
                    ->ordered()
                    ->value('handle');
            }
        }

        parent::mount();
    }

    public function table(Table $table): Table
    {
        $collection = $this->resolveCollection();

        return EntriesTable::table($table, $collection)
            ->modifyQueryUsing(function (Builder $query): void {
                $resolvedCollection = $this->resolveCollection();

                if (! $resolvedCollection) {
                    return;
                }

                $query->where('collection_id', $resolvedCollection->id);
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
        return $this->resolveCollection()?->name ?? 'Položky';
    }

    private function resolveCollection(): ?Collection
    {
        if ($this->hasResolvedCollection) {
            return $this->resolvedCollection;
        }

        $this->hasResolvedCollection = true;

        $this->resolvedCollection = EntryResource::getCurrentCollection();

        if ($this->resolvedCollection) {
            return $this->resolvedCollection;
        }

        if (blank($this->collectionHandle)) {
            return null;
        }

        $this->resolvedCollection = Collection::where('handle', $this->collectionHandle)->first();

        return $this->resolvedCollection;
    }
}
