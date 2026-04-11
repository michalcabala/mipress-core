<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\CollectionResource;

class CreateCollection extends CreateRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = CollectionResource::class;
}
