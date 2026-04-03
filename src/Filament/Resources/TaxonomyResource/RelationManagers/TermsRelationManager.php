<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MiPress\Core\Models\Term;

class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $title = 'Termy';

    protected static \BackedEnum|string|null $icon = 'fal-tags';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Název')
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label('Slug')
                ->maxLength(255)
                ->helperText('Automaticky z názvu. Lze upravit.'),
            Select::make('parent_id')
                ->label('Nadřazený term')
                ->options(fn (): array => Term::query()
                    ->where('taxonomy_id', $this->getOwnerRecord()->getKey())
                    ->whereNull('parent_id')
                    ->pluck('title', 'id')
                    ->toArray()
                )
                ->nullable()
                ->searchable()
                ->visible(fn (): bool => (bool) $this->getOwnerRecord()->is_hierarchical),
            TextInput::make('sort_order')
                ->label('Pořadí')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label('Nadřazený')
                    ->default('—'),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label('Položek')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
