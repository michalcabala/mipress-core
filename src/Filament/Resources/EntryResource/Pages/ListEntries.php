<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\WithResourceLockIndicator;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\View\PanelsRenderHook;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Resources\Concerns\HasRecordStateLinks;
use MiPress\Core\Filament\Resources\EntryResource\Tables\EntriesTable;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class ListEntries extends ListRecords
{
    use HasRecordStateLinks;
    use WithResourceLockIndicator;

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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                View::make('mipress::filament.components.record-state-links')
                    ->key('record-state-links')
                    ->viewData(fn (): array => ['items' => $this->getRecordStateLinks()]),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
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

    protected function getRecordStateLinksQuery(): Builder
    {
        return Entry::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->when(
                $this->resolveCollection(),
                fn (Builder $query, Collection $collection): Builder => $query->where('collection_id', $collection->id),
            );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRecordStateLinksRouteParameters(): array
    {
        return filled($this->collectionHandle)
            ? ['collection' => $this->collectionHandle]
            : [];
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
