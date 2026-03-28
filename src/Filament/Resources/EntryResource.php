<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\EntryResource\Pages\CreateEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\EditEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\ListEntries;
use MiPress\Core\Filament\Resources\EntryResource\RelationManagers\AuditLogsRelationManager;
use MiPress\Core\Filament\Resources\EntryResource\Schemas\EntryForm;
use MiPress\Core\Filament\Resources\EntryResource\Tables\EntriesTable;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-file-lines';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?string $modelLabel = 'Položka';

    protected static ?string $pluralModelLabel = 'Položky';

    public static function getNavigationItems(): array
    {
        return Collection::ordered()
            ->get()
            ->map(fn (Collection $collection) => NavigationItem::make($collection->name)
                ->icon($collection->icon ?? 'heroicon-o-document')
                ->group('Obsah')
                ->sort($collection->sort_order)
                ->url(static::getUrl('index', ['collection' => $collection->handle]))
                ->isActiveWhen(fn () => request()->query('collection') === $collection->handle)
            )
            ->toArray();
    }

    public static function getCurrentCollection(): ?Collection
    {
        $handle = request()->query('collection');

        if (! $handle) {
            return null;
        }

        return Collection::where('handle', $handle)->with('blueprint')->first();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        $collection = static::getCurrentCollection();

        if ($collection) {
            $query->where('collection_id', $collection->id);
        }

        if (auth()->user()?->hasRole(UserRole::Contributor->value)) {
            $query->where('author_id', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return EntryForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return EntriesTable::table($table);
    }

    public static function getRelationManagers(): array
    {
        return [
            AuditLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'create' => CreateEntry::route('/create'),
            'edit' => EditEntry::route('/{record}/edit'),
        ];
    }
}
