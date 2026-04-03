<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CollectionsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->html()
                    ->formatStateUsing(fn (?string $state) => $state
                        ? '<x-dynamic-component :component="'.e($state).'" class="h-5 w-5" />'
                        : '')
                    ->width('40px'),
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('blueprint.name')
                    ->label('Šablona')
                    ->sortable(),
                IconColumn::make('dated')
                    ->label('Datovaný')
                    ->boolean(),
                IconColumn::make('hierarchical')
                    ->label('Hierarchie')
                    ->boolean(),
                TextColumn::make('entries_count')
                    ->label('Záznamů')
                    ->counts('entries')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
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
