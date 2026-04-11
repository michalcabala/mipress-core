<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\TaxonomyResource;

class CreateTaxonomy extends CreateRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = TaxonomyResource::class;
}
