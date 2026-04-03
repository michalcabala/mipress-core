<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use MiPress\Core\Filament\Resources\TaxonomyResource\Pages\CreateTaxonomy;
use MiPress\Core\Filament\Resources\TaxonomyResource\Pages\EditTaxonomy;
use MiPress\Core\Filament\Resources\TaxonomyResource\Pages\ListTaxonomies;
use MiPress\Core\Filament\Resources\TaxonomyResource\RelationManagers\TermsRelationManager;
use MiPress\Core\Filament\Resources\TaxonomyResource\Schemas\TaxonomyForm;
use MiPress\Core\Filament\Resources\TaxonomyResource\Tables\TaxonomiesTable;
use MiPress\Core\Models\Taxonomy;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-sitemap';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $modelLabel = 'Třídění';

    protected static ?string $pluralModelLabel = 'Třídění';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::table($table);
    }

    public static function getRelationManagers(): array
    {
        return [
            TermsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxonomies::route('/'),
            'create' => CreateTaxonomy::route('/create'),
            'edit' => EditTaxonomy::route('/{record}/edit'),
        ];
    }
}
