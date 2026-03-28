<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základní informace')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('handle')
                        ->label('Handle')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Unikátní identifikátor (např. pages, articles)'),
                ]),
                Grid::make(2)->schema([
                    Select::make('blueprint_id')
                        ->label('Šablona')
                        ->relationship('blueprint', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('icon')
                        ->label('Ikona')
                        ->nullable()
                        ->maxLength(100)
                        ->placeholder('fas-file-lines')
                        ->helperText('Název Blade ikony (např. fas-file-lines)'),
                ]),
            ]),

            Section::make('Nastavení obsahu')->schema([
                Grid::make(2)->schema([
                    Toggle::make('dated')
                        ->label('Datovaný obsah')
                        ->helperText('Záznamy mají datum publikování'),
                    Toggle::make('slugs')
                        ->label('Používat slug')
                        ->default(true),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('route')
                        ->label('URL vzor')
                        ->nullable()
                        ->maxLength(255)
                        ->placeholder('/{slug}'),
                    Select::make('sort_direction')
                        ->label('Směr řazení')
                        ->options([
                            'asc' => 'Vzestupně',
                            'desc' => 'Sestupně',
                        ])
                        ->default('asc'),
                ]),
                TextInput::make('sort_order')
                    ->label('Pořadí v navigaci')
                    ->numeric()
                    ->default(0),
            ]),
        ]);
    }
}
