<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Základní informace')
                            ->description('Definujte identitu sekce a základní nastavení editoru.')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Název')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Např. Články')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                $currentHandle = trim((string) ($get('handle') ?? ''));
                                                $oldHandle = Str::slug((string) $old);

                                                if ($currentHandle !== '' && $currentHandle !== $oldHandle) {
                                                    return;
                                                }

                                                $set('handle', Str::slug((string) $state));
                                            }),
                                        TextInput::make('handle')
                                            ->label('Handle')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder('articles')
                                            ->helperText('Interní identifikátor sekce, například `pages` nebo `articles`.'),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('blueprint_id')
                                            ->label('Šablona')
                                            ->relationship('blueprint', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->nullable()
                                            ->helperText('Volitelné výchozí pole pro záznamy této sekce.'),
                                        TextInput::make('icon')
                                            ->label('Ikona')
                                            ->nullable()
                                            ->maxLength(100)
                                            ->placeholder('fal-file-lines')
                                            ->helperText('Blade icon alias pro navigaci a přehledy.'),
                                    ]),
                            ]),
                        Section::make('URL a řazení')
                            ->description('Nastavte veřejnou URL strukturu a výchozí pořadí záznamů.')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('route')
                                            ->label('URL vzor')
                                            ->nullable()
                                            ->maxLength(255)
                                            ->placeholder(fn (Get $get): string => (bool) $get('slugs') ? '/articles/{slug}' : '/articles')
                                            ->helperText(fn (Get $get): string => (bool) $get('slugs')
                                                ? 'Pro detail záznamu použijte placeholder `{slug}`.'
                                                : 'Když jsou slugy vypnuté, můžete použít i pevnou URL bez placeholderů.'),
                                        Select::make('sort_direction')
                                            ->label('Směr řazení')
                                            ->options([
                                                'asc' => 'Vzestupně',
                                                'desc' => 'Sestupně',
                                            ])
                                            ->default('asc')
                                            ->native(false),
                                    ]),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make('Chování sekce')
                            ->schema([
                                Toggle::make('dated')
                                    ->label('Datovaný obsah')
                                    ->helperText('Záznamy budou počítat s datem publikování.')
                                    ->inline(false),
                                Toggle::make('slugs')
                                    ->label('Používat slug')
                                    ->default(true)
                                    ->live()
                                    ->helperText('Zapněte pro vlastní detail URL jednotlivých záznamů.')
                                    ->inline(false),
                                Toggle::make('hierarchical')
                                    ->label('Hierarchická struktura')
                                    ->default(false)
                                    ->helperText('Vhodné pro sekce typu stránky nebo dokumentace.')
                                    ->inline(false),
                                TextInput::make('sort_order')
                                    ->label('Pořadí v navigaci')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Nižší číslo = dříve v administraci.'),
                            ]),
                        Section::make('Třídění')
                            ->description('Vyberte taxonomie dostupné při editaci záznamů této sekce.')
                            ->schema([
                                Select::make('taxonomies')
                                    ->label('Přiřazená třídění')
                                    ->relationship('taxonomies', 'title')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->nullable(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }
}
