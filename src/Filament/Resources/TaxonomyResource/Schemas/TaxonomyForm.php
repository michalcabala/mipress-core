<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MiPress\Core\Models\Collection;

class TaxonomyForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základní informace')->schema([
                Grid::make(2)->schema([
                    TextInput::make('title')
                        ->label('Název')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('handle')
                        ->label('Handle')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Unikátní identifikátor (např. categories, tags)')
                        ->disabled(fn ($record) => $record !== null),
                ]),
                Textarea::make('description')
                    ->label('Popis')
                    ->nullable()
                    ->rows(2),
            ]),

            Section::make('Nastavení')->schema([
                Grid::make(2)->schema([
                    Toggle::make('is_hierarchical')
                        ->label('Hierarchická struktura')
                        ->helperText('Termy mohou mít podtermy'),
                    Select::make('blueprint_id')
                        ->label('Šablona termů')
                        ->relationship('blueprint', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->helperText('Prázdné = termy mají jen název a slug'),
                ]),
            ]),

            Section::make('Přiřazení ke kolekcím')->schema([
                Select::make('collections')
                    ->label('Kolekce')
                    ->relationship('collections', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Vyberte kolekce, ve kterých se tato taxonomie zobrazí'),
            ]),
        ]);
    }
}
