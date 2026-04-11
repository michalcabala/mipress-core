<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Filament\Resources\Concerns\HasRecordStateTabs;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;

class ListPages extends ListRecords
{
    use HasRecordStateTabs;

    protected static string $resource = PageResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->with('resourceLock');
    }

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

    protected function getRecordStateTabsBaseQuery(): Builder
    {
        return Page::query()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
