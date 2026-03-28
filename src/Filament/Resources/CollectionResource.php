<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\CreateCollection;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\EditCollection;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\ListCollections;
use MiPress\Core\Filament\Resources\CollectionResource\Schemas\CollectionForm;
use MiPress\Core\Filament\Resources\CollectionResource\Tables\CollectionsTable;
use MiPress\Core\Models\Collection;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-layer-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $modelLabel = 'Sekce';

    protected static ?string $pluralModelLabel = 'Sekce';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CollectionForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return CollectionsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
            'create' => CreateCollection::route('/create'),
            'edit' => EditCollection::route('/{record}/edit'),
        ];
    }
}
