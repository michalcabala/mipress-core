<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\BlueprintResource;

class CreateBlueprint extends CreateRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = BlueprintResource::class;
}
