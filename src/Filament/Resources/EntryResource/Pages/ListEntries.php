<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Resources\EntryResource\Tables\EntriesTable;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class ListEntries extends ListRecords
{
    public string $collectionHandle = '';

    private bool $hasResolvedCollection = false;

    private ?Collection $resolvedCollection = null;

    protected static string $resource = EntryResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

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

    protected function loadDefaultActiveTab(): void
    {
        parent::loadDefaultActiveTab();

        if (filled($this->activeTab) && ! array_key_exists($this->activeTab, $this->getCachedTabs())) {
            $this->activeTab = $this->getDefaultActiveTab();
        }
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $collectionId = $this->resolveCollection()?->id;

        $baseQuery = Entry::query()
            ->when($collectionId, fn (Builder $q): Builder => $q->where('collection_id', $collectionId));

        $allCount = (clone $baseQuery)->count();

        $tabs = [
            'all' => Tab::make('Vše')
                ->icon('far-layer-group')
                ->badge($allCount)
                ->deferBadge()
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()),
        ];

        foreach (EntryStatus::cases() as $status) {
            $count = (clone $baseQuery)->where('status', $status)->count();

            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge($count)
                ->badgeColor($status->getColor())
                ->deferBadge()
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()->where('status', $status));
        }

        $trashedCount = (clone $baseQuery)->onlyTrashed()->count();

        $tabs['trashed'] = Tab::make('Koš')
            ->icon('far-trash-can')
            ->badge($trashedCount ?: null)
            ->badgeColor('danger')
            ->deferBadge()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed())
            ->excludeQueryWhenResolvingRecord();

        return $tabs;
    }

    public function table(Table $table): Table
    {
        $collection = $this->resolveCollection();
        $suffix = filled($this->collectionHandle) ? str($this->collectionHandle)->studly()->toString() : 'Index';

        return EntriesTable::table($table, $collection)
            ->queryStringIdentifier('entries'.$suffix)
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
        return $this->resolveCollection()?->name ?? 'PoloĹľky';
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

        $this->resolvedCollection = EntryResource::resolveCollectionByHandle($this->collectionHandle);

        return $this->resolvedCollection;
    }
}
