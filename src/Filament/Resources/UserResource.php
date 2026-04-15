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
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource\Pages\CreateUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\EditUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\ListUsers;
use MiPress\Core\Filament\Resources\UserResource\Schemas\UserForm;
use MiPress\Core\Filament\Resources\UserResource\Tables\UsersTable;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-user-group-crown';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('mipress::admin.resources.user.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('mipress::admin.resources.user.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mipress::admin.resources.user.plural_model_label');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function canCreate(): bool
    {
        return self::isAdminOrAbove();
    }

    public static function canEdit(Model $record): bool
    {
        if (! self::isAdminOrAbove()) {
            return false;
        }

        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $record->isSuperAdmin()) {
            return true;
        }

        return $user->isSuperAdmin();
    }

    public static function canDelete(Model $record): bool
    {
        return self::isAdminOrAbove() && ! $record->isSuperAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return self::isAdminOrAbove();
    }

    public static function canForceDelete(Model $record): bool
    {
        return self::isAdminOrAbove() && ! $record->isSuperAdmin();
    }

    private static function isAdminOrAbove(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
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
