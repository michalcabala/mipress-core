<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use MiPress\Core\Filament\Clusters\ContentCluster;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\CreateBlueprint;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\EditBlueprint;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\ListBlueprints;
use MiPress\Core\Filament\Resources\BlueprintResource\Schemas\BlueprintForm;
use MiPress\Core\Filament\Resources\BlueprintResource\Tables\BlueprintsTable;
use MiPress\Core\Models\Blueprint;

class BlueprintResource extends Resource
{
    protected static ?string $model = Blueprint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-pen-ruler';

    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('mipress::admin.resources.blueprint.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mipress::admin.resources.blueprint.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return BlueprintForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return BlueprintsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlueprints::route('/'),
            'create' => CreateBlueprint::route('/create'),
            'edit' => EditBlueprint::route('/{record}/edit'),
        ];
    }
}
