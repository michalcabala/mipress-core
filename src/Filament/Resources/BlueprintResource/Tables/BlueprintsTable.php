<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Tables;

use Filament\Actions\ActionGroup;
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
                    ->label(__('mipress::admin.resources.blueprint.table.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label(__('mipress::admin.resources.blueprint.table.columns.handle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collections_count')
                    ->label(__('mipress::admin.resources.blueprint.table.columns.collections_count'))
                    ->counts('collections')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('mipress::admin.resources.blueprint.table.columns.updated_at'))
                    ->isoDateTime('LLL')
                    ->description(fn ($record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                        ? __('mipress::admin.common.created_at_description', ['date' => $record->created_at->isoFormat('LLL')])
                        : null)
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
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
