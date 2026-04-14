<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Widgets;

use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Widgets\PublicationStatusOverviewWidget;
use MiPress\Core\Models\Entry;

class EntryPublicationStatusOverview extends PublicationStatusOverviewWidget
{
    public ?int $collectionId = null;

    public ?string $collectionHandle = null;

    /**
     * @return class-string
     */
    protected function getStatusOverviewModelClass(): string
    {
        return Entry::class;
    }

    protected function getStatusOverviewUrl(): string
    {
        return EntryResource::getUrl('index', EntryResource::collectionUrlParameters($this->collectionHandle));
    }

    protected function getTableQueryStringIdentifier(): string
    {
        $suffix = filled($this->collectionHandle)
            ? str($this->collectionHandle)->studly()->toString()
            : 'Index';

        return 'entries'.$suffix;
    }

    protected function scopeStatusOverviewQuery(Builder $query): Builder
    {
        return $query->when(
            filled($this->collectionId),
            fn (Builder $scopedQuery): Builder => $scopedQuery->where('collection_id', $this->collectionId),
        );
    }
}
