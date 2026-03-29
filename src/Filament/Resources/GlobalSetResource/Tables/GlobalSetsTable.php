<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GlobalSetsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data')
                    ->label('Počet položek')
                    ->formatStateUsing(fn (array $state): string => (string) count($state))
                    ->sortable(false),
                TextColumn::make('updated_at')
                    ->label('Aktualizováno')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('title')
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
