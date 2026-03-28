<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use MiPress\Core\Enums\UserRole;

class UserForm
{
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
}
