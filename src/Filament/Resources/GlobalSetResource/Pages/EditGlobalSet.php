<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Filament\Resources\GlobalSetResource;

class EditGlobalSet extends EditRecord
{
    protected static string $resource = GlobalSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
