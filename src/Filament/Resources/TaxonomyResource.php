<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use MiPress\Core\Filament\Clusters\ContentCluster;
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

    protected static string|\BackedEnum|null $navigationIcon = 'fal-list-tree';

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $modelLabel = 'Taxonomie';

    protected static ?string $pluralModelLabel = 'Taxonomie';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::table($table);
    }

    public static function getRelations(): array
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
