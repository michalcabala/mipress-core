<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource\Pages\CreateUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\EditUser;
use MiPress\Core\Filament\Resources\UserResource\Pages\ListUsers;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-user-group-crown';

    protected static string|\UnitEnum|null $navigationGroup = 'Uživatelé';

    protected static ?string $modelLabel = 'Uživatel';

    protected static ?string $pluralModelLabel = 'Uživatelé';

    protected static ?int $navigationSort = 10;

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
        return $schema->components([
            Section::make('Základní informace')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Jméno')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label('E-mail')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(User::class, 'email', ignoreRecord: true),
                        ]),
                ]),

            Section::make('Heslo')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('password')
                                ->label('Nové heslo')
                                ->password()
                                ->revealable()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                                ->confirmed()
                                ->minLength(8),

                            TextInput::make('password_confirmation')
                                ->label('Heslo (potvrzení)')
                                ->password()
                                ->revealable()
                                ->required(fn (string $context): bool => $context === 'create')
                                ->dehydrated(false),
                        ]),
                ])
                ->collapsible()
                ->collapsed(fn (string $context): bool => $context === 'edit'),

            Section::make('Role a oprávnění')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('role')
                                ->label('Role')
                                ->options(UserRole::class)
                                ->required()
                                ->disabled(fn (?Model $record): bool => (bool) $record?->isSuperAdmin()),

                            DateTimePicker::make('email_verified_at')
                                ->label('E-mail ověřen')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Jméno')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (UserRole::tryFrom($state)?->getLabel() ?? $state)
                        : '—'
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        UserRole::SuperAdmin->value => 'danger',
                        UserRole::Admin->value => 'warning',
                        UserRole::Editor->value => 'success',
                        UserRole::Contributor->value => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('email_verified_at')
                    ->label('Ověřen')
                    ->boolean()
                    ->trueIcon('fal-badge-check')
                    ->falseIcon('fal-circle-xmark')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),

                TextColumn::make('created_at')
                    ->label('Vytvořen')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deleted_at')
                    ->label('Smazán')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->isSuperAdmin()),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->isSuperAdmin()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
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
