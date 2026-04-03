<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\TaxonomyResource;

class ListTaxonomies extends ListRecords
{
    protected static string $resource = TaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
