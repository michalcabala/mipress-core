<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\TermResource;

class CreateTerm extends CreateRecord
{
    protected static string $resource = TermResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['taxonomy_id'] = request()->query('taxonomy_id', $data['taxonomy_id'] ?? null);

        return $data;
    }
}
