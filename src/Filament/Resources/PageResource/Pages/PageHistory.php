<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use MiPress\Core\Filament\Resources\EntryResource\Pages\EntryHistory;
use MiPress\Core\Filament\Resources\PageResource;

class PageHistory extends EntryHistory
{
    protected static string $resource = PageResource::class;
}
