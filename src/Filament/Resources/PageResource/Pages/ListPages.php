<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use MiPress\Core\Filament\Resources\EntryResource\Pages\ListEntries;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;

class ListPages extends ListEntries
{
    protected static string $resource = PageResource::class;

    public string $collectionHandle = Page::COLLECTION_HANDLE;

    public function mount(?string $collection = null): void
    {
        parent::mount(Page::COLLECTION_HANDLE);
    }
}
