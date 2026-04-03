<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Filament\Resources\TaxonomyResource;

class EditTaxonomy extends EditRecord
{
    protected static string $resource = TaxonomyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
