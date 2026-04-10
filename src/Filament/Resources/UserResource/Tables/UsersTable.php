<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource;

class UsersTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles']))
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->height(40)
                    ->width(40)
                    ->circular()
                    ->checkFileExistence(false)
                    ->state(fn (User $record): ?string => $record->getFilamentAvatarUrl()),

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

                IconColumn::make('has_email_authentication')
                    ->label('MFA')
                    ->boolean()
                    ->trueIcon('fal-shield-check')
                    ->falseIcon('fal-shield-xmark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Vytvořen')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deleted_at')
                    ->label('Smazán')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(UserRole::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, string $role): Builder => $query->role($role),
                    )),
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed() && UserResource::canEdit($record)),
                    DeleteAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed() && ! $record->isSuperAdmin()),
                    RestoreAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && $record->trashed()),
                    ForceDeleteAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && $record->trashed() && ! $record->isSuperAdmin()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                ]),
            ]);
    }

    private static function canManageUsers(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
