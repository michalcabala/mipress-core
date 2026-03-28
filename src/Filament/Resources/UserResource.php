<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Filament\Resources\UserResource\Pages\CreateUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\EditUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\ListUsers;
use MiPress\Core\Filament\Resources\UserResource\Schemas\UserForm;
use MiPress\Core\Filament\Resources\UserResource\Tables\UsersTable;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-user-group-crown';

    protected static string|\UnitEnum|null $navigationGroup = 'Uživatelé';

    protected static ?string $modelLabel = 'Uživatel';

    protected static ?string $pluralModelLabel = 'Uživatelé';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isSuperAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function canForceDelete(Model $record): bool
    {
        return ! $record->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::table($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
