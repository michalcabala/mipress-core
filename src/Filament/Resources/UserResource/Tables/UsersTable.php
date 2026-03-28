<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use MiPress\Core\Enums\UserRole;

class UsersTable
{
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
}
