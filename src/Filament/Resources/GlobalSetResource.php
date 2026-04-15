<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\GlobalSetResource\Pages\CreateGlobalSet;
use MiPress\Core\Filament\Resources\GlobalSetResource\Pages\EditGlobalSet;
use MiPress\Core\Filament\Resources\GlobalSetResource\Pages\ListGlobalSets;
use MiPress\Core\Filament\Resources\GlobalSetResource\Schemas\GlobalSetForm;
use MiPress\Core\Filament\Resources\GlobalSetResource\Tables\GlobalSetsTable;
use MiPress\Core\Models\GlobalSet;

class GlobalSetResource extends Resource
{
    protected static ?string $model = GlobalSet::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-globe';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('mipress::admin.resources.global_set.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('mipress::admin.resources.global_set.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mipress::admin.resources.global_set.plural_model_label');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return GlobalSetForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return GlobalSetsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGlobalSets::route('/'),
            'create' => CreateGlobalSet::route('/create'),
            'edit' => EditGlobalSet::route('/{record}/edit'),
        ];
    }
}
