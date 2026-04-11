<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\GlobalSetResource;

class CreateGlobalSet extends CreateRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = GlobalSetResource::class;
}
