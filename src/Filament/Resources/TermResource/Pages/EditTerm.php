<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\TermResource;

class EditTerm extends EditRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = TermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
