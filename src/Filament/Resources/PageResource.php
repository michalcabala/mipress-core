<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page as FilamentPage;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Filament\Resources\PageResource\Pages\CreatePage;
use MiPress\Core\Filament\Resources\PageResource\Pages\EditPage;
use MiPress\Core\Filament\Resources\PageResource\Pages\EditPageSeo;
use MiPress\Core\Filament\Resources\PageResource\Pages\ListPages;
use MiPress\Core\Filament\Resources\PageResource\Pages\PageHistory;
use MiPress\Core\Filament\Resources\PageResource\Schemas\PageForm;
use MiPress\Core\Filament\Resources\PageResource\Tables\PagesTable;
use MiPress\Core\Models\Page;

class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-file-lines';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'pages';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('mipress::admin.resources.page.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('mipress::admin.resources.page.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mipress::admin.resources.page.plural_model_label');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::table($table);
    }

    public static function getRecordSubNavigation(FilamentPage $page): array
    {
        return $page->generateNavigationItems([
            EditPage::class,
            EditPageSeo::class,
            PageHistory::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
            'seo' => EditPageSeo::route('/{record}/seo'),
            'history' => PageHistory::route('/{record}/history'),
        ];
    }
}
