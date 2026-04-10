<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\WithResourceLockIndicator;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Filament\Resources\Concerns\HasRecordStateLinks;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;

class ListPages extends ListRecords
{
    use HasRecordStateLinks;
    use WithResourceLockIndicator;

    protected static string $resource = PageResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                View::make('mipress::filament.components.record-state-links')
                    ->key('record-state-links')
                    ->viewData(fn (): array => ['items' => $this->getRecordStateLinks()]),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
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

    protected function getRecordStateLinksQuery(): Builder
    {
        return Page::query()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
