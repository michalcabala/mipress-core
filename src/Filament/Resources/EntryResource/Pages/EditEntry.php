<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use MiPress\Core\Filament\Resources\EntryResource;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        $collection = $this->getRecord()->collection;

        return static::$resource::getUrl('index', [
            'collection' => $collection?->handle,
        ]);
    }
}
