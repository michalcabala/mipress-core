<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Resources\PageResource\Widgets\PagePublicationStatusOverview;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with(['author', 'parent']);
    }

    public function table(Table $table): Table
    {
        return $table->queryStringIdentifier('pages');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PagePublicationStatusOverview::make(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('mipress::admin.resources.page.pages.list_title');
    }
}
