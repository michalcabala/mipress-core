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
                    ->label(__('mipress::admin.resources.collection.table.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label(__('mipress::admin.resources.collection.table.columns.handle'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge(),
                TextColumn::make('blueprint.name')
                    ->label(__('mipress::admin.resources.collection.table.columns.blueprint'))
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('dated')
                    ->label(__('mipress::admin.resources.collection.table.columns.dated'))
                    ->boolean(),
                IconColumn::make('hierarchical')
                    ->label(__('mipress::admin.resources.collection.table.columns.hierarchical'))
                    ->boolean(),
                TextColumn::make('route')
                    ->label(__('mipress::admin.resources.collection.table.columns.route'))
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->default(__('mipress::admin.common.empty')),
                TextColumn::make('entries_count')
                    ->label(__('mipress::admin.resources.collection.table.columns.entries_count'))
                    ->counts('entries')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('mipress::admin.resources.collection.table.columns.sort_order'))
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
