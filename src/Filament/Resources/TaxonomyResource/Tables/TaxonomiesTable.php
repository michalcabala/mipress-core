<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxonomiesTable
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
                IconColumn::make('is_hierarchical')
                    ->label('Hierarchie')
                    ->boolean(),
                TextColumn::make('blueprint.name')
                    ->label('Šablona')
                    ->default('—')
                    ->sortable(),
                TextColumn::make('terms_count')
                    ->label('Termů')
                    ->counts('terms')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('title')
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
