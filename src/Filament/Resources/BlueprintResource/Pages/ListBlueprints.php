<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\BlueprintResource;

class ListBlueprints extends ListRecords
{
    protected static string $resource = BlueprintResource::class;

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
