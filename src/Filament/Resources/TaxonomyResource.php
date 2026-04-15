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

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 30;

    public static function getModelLabel(): string
    {
        return __('mipress::admin.resources.taxonomy.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mipress::admin.resources.taxonomy.plural_model_label');
    }

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
