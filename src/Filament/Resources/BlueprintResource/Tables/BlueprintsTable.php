<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlueprintsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collections_count')
                    ->label('Sekcí')
                    ->counts('collections')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
