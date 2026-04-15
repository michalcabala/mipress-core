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
            Section::make(__('mipress::admin.resources.user.form.sections.basic_information'))
                ->schema([
                    FileUpload::make('avatar_path')
                        ->label(__('mipress::admin.resources.user.form.fields.avatar'))
                        ->helperText(__('mipress::admin.resources.user.form.help.avatar'))
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
                                ->label(__('mipress::admin.resources.user.form.fields.name'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label(__('mipress::admin.resources.user.form.fields.email'))
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(User::class, 'email', ignoreRecord: true),
                        ]),
                ]),

            Section::make(__('mipress::admin.resources.user.form.sections.roles_and_permissions'))
                ->schema([
                    Select::make('role')
                        ->label(__('mipress::admin.resources.user.form.fields.role'))
                        ->options(UserRole::class)
                        ->required()
                        ->disabled(fn (?Model $record): bool => (bool) $record?->isSuperAdmin()),
                ]),
        ]);
    }
}
