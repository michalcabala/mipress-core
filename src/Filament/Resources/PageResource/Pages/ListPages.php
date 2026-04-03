<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use MiPress\Core\Filament\Resources\PageResource;
use Openplain\FilamentTreeView\Resources\Pages\TreePage;

class ListPages extends TreePage
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return 'Stránky';
    }
}
