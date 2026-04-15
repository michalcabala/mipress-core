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
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.title'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Taxonomy $record): ?string => filled($record->description) ? (string) str($record->description)->limit(90) : null),
                TextColumn::make('handle')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.handle'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->copyable(),
                TextColumn::make('collection.name')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.collection'))
                    ->sortable()
                    ->toggleable()
                    ->default(__('mipress::admin.common.empty')),
                IconColumn::make('is_hierarchical')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.hierarchical'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('blueprint.name')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.blueprint'))
                    ->default(__('mipress::admin.common.empty'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('terms_count')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.terms_count'))
                    ->counts('terms')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('mipress::admin.resources.taxonomy.table.columns.updated_at'))
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
