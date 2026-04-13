<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\UserRole;

class UserForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základní informace')
                ->schema([
                    FileUpload::make('avatar_path')
                        ->label('Avatar')
                        ->helperText('Profilová fotka uživatele v administraci.')
                        ->avatar()
                        ->imageEditor()
                        ->circleCropper()
                        ->disk('public')
                        ->directory('avatars/users')
                        ->visibility('public')
                        ->moveFiles()
                        ->maxSize(2048)
                        ->imageResizeTargetWidth('512')
                        ->imageResizeTargetHeight('512'),
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

            Section::make('Role a oprávnění')
                ->schema([
                    Select::make('role')
                        ->label('Role')
                        ->options(UserRole::class)
                        ->required()
                        ->disabled(fn (?Model $record): bool => (bool) $record?->isSuperAdmin()),
                ]),
        ]);
    }
}
