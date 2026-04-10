<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\CollectionResource;

class ListCollections extends ListRecords
{
    protected static string $resource = CollectionResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
