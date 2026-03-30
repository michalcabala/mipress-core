<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Builder;

class Page extends Entry
{
    public const COLLECTION_HANDLE = 'pages';

    protected static function booted(): void
    {
        static::addGlobalScope('pages_collection', function (Builder $query): void {
            $query->whereHas('collection', function (Builder $collectionQuery): void {
                $collectionQuery->where('handle', self::COLLECTION_HANDLE);
            });
        });

        static::creating(function (self $page): void {
            if (filled($page->collection_id)) {
                return;
            }

            $pagesCollectionId = Collection::query()
                ->where('handle', self::COLLECTION_HANDLE)
                ->value('id');

            if (is_numeric($pagesCollectionId)) {
                $page->collection_id = (int) $pagesCollectionId;
            }
        });
    }
}
