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
use MiPress\Core\Models\Taxonomy;

class TaxonomiesTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Taxonomy $record): ?string => filled($record->description) ? (string) str($record->description)->limit(90) : null),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->copyable(),
                TextColumn::make('collection.name')
                    ->label('Kolekce')
                    ->sortable()
                    ->toggleable()
                    ->default('—'),
                IconColumn::make('is_hierarchical')
                    ->label('Hierarchie')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('blueprint.name')
                    ->label('Šablona')
                    ->default('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('terms_count')
                    ->label('Termů')
                    ->counts('terms')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Datum')
                    ->isoDateTime('LLL')
                    ->description(fn ($record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                        ? 'Vytvořeno '.$record->created_at->isoFormat('LLL')
                        : null)
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
