<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TaxonomyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->schema([
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 3])
                        ->schema([
                            Section::make('Základní informace')
                                ->icon('fal-tag')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('title')
                                            ->label('Název')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Např. Kategorie')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                if (($get('handle') ?? '') !== Str::slug($old)) {
                                                    return;
                                                }

                                                $set('handle', Str::slug($state));
                                            }),
                                        TextInput::make('handle')
                                            ->label('Handle')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('kategorie')
                                            ->helperText('Automaticky z názvu. Lze upravit při vytváření.')
                                            ->disabled(fn ($record): bool => $record !== null),
                                    ]),
                                    Textarea::make('description')
                                        ->label('Popis')
                                        ->nullable()
                                        ->rows(3)
                                        ->helperText('Krátké vysvětlení, kde a jak se taxonomie používá.'),
                                ]),
                            Section::make('Zobrazení v Entries tabulce')
                                ->icon('fal-table-columns')
                                ->collapsible()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Toggle::make('show_in_entries_table')
                                            ->label('Zobrazit sloupec')
                                            ->default(true),
                                        Toggle::make('show_in_entries_filter')
                                            ->label('Zobrazit filtr')
                                            ->default(true),
                                    ]),
                                    Grid::make(2)->schema([
                                        Toggle::make('searchable_in_entries_table')
                                            ->label('Sloupec je prohledávatelný')
                                            ->default(false),
                                        Toggle::make('sortable_in_entries_table')
                                            ->label('Sloupec je řaditelný')
                                            ->default(false),
                                    ]),
                                    Grid::make(2)->schema([
                                        Select::make('entries_table_display_mode')
                                            ->label('Režim zobrazení')
                                            ->options([
                                                'badges' => 'Štítky',
                                                'text' => 'Text',
                                            ])
                                            ->default('badges')
                                            ->required()
                                            ->native(false)
                                            ->live(),
                                        Select::make('entries_table_badge_palette')
                                            ->label('Paleta štítků')
                                            ->options([
                                                'neutral' => 'Neutrální',
                                                'primary' => 'Primární',
                                                'success' => 'Úspěch',
                                                'warning' => 'Upozornění',
                                                'danger' => 'Nebezpečí',
                                                'info' => 'Informace',
                                            ])
                                            ->default('neutral')
                                            ->required()
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => ($get('entries_table_display_mode') ?? 'badges') === 'badges'),
                                    ]),
                                ]),
                        ]),
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 1])
                        ->schema([
                            Section::make('Nastavení')
                                ->icon('fal-gear')
                                ->schema([
                                    Toggle::make('is_hierarchical')
                                        ->label('Hierarchická struktura')
                                        ->helperText('Termy mohou mít podtermy.'),
                                    Select::make('blueprint_id')
                                        ->label('Šablona termů')
                                        ->relationship('blueprint', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Prázdné = termy mají jen název a slug.'),
                                ]),
                            Section::make('Přiřazení ke kolekci')
                                ->icon('fal-folder-tree')
                                ->schema([
                                    Select::make('collection_id')
                                        ->label('Kolekce')
                                        ->relationship('collection', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Vyberte kolekci, ve které se taxonomie zobrazí.'),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }
}
