<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\GlobalSetResource;

class ListGlobalSets extends ListRecords
{
    protected static string $resource = GlobalSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
