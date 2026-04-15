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
                    ->label(__('mipress::admin.resources.global_set.table.columns.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label(__('mipress::admin.resources.global_set.table.columns.handle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('data_count')
                    ->label(__('mipress::admin.resources.global_set.table.columns.data_count'))
                    ->state(fn ($record): int => is_array($record->data) ? count($record->data) : 0)
                    ->sortable(false),
                TextColumn::make('updated_at')
                    ->label(__('mipress::admin.resources.global_set.table.columns.updated_at'))
                    ->isoDateTime('LLL')
                    ->description(fn ($record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                        ? __('mipress::admin.common.created_at_description', ['date' => $record->created_at->isoFormat('LLL')])
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
            ]);
    }
}
