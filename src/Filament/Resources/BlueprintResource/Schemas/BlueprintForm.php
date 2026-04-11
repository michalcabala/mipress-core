<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use MiPress\Core\FieldTypes\FieldTypeRegistry;

class BlueprintForm
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
                        ->helperText('Unikátní identifikátor (např. page, article)'),
                ]),
            ]),

            Section::make('Sekce a pole')->schema([
                Repeater::make('fields')
                    ->label('Sekce')
                    ->schema([
                        TextInput::make('section')
                            ->label('Název sekce')
                            ->required()
                            ->maxLength(255),
                        Repeater::make('fields')
                            ->label('Pole')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('handle')
                                        ->label('Handle')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('label')
                                        ->label('Popisek')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('type')
                                        ->label('Typ pole')
                                        ->required()
                                        ->options(fn (): array => app(FieldTypeRegistry::class)->groupedOptions())
                                        ->searchable()
                                        ->live(),
                                    Select::make('required')
                                        ->label('Povinné')
                                        ->options([
                                            '0' => 'Ne',
                                            '1' => 'Ano',
                                        ])
                                        ->default('0'),
                                ]),
                                Section::make('Zobrazení v tabulce')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Checkbox::make('show_in_table')
                                                ->label('Zobrazit v tabulce')
                                                ->default(false),
                                            Checkbox::make('searchable')
                                                ->label('Prohledávatelné')
                                                ->default(false),
                                            Checkbox::make('sortable')
                                                ->label('Řaditelné')
                                                ->default(false),
                                        ]),
                                    ])
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                                Section::make('Nastavení pole')
                                    ->schema(fn (Get $get): array => static::getFieldTypeSettings($get('type')))
                                    ->visible(fn (Get $get): bool => static::hasFieldTypeSettings($get('type')))
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                                Section::make('Podmíněné zobrazení')
                                    ->schema([
                                        Select::make('config.visibility_mode')
                                            ->label('Vyhodnocení podmínek')
                                            ->options([
                                                'all' => 'Všechny podmínky (AND)',
                                                'any' => 'Alespoň jedna podmínka (OR)',
                                            ])
                                            ->default('all')
                                            ->native(false),
                                        Repeater::make('config.visibility_conditions')
                                            ->label('Podmínky')
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    TextInput::make('field')
                                                        ->label('Handle pole')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->helperText('Např. title, category nebo data.nested.value.'),
                                                    Select::make('operator')
                                                        ->label('Operátor')
                                                        ->required()
                                                        ->options([
                                                            'equals' => 'Rovná se',
                                                            'not_equals' => 'Nerovná se',
                                                            'contains' => 'Obsahuje',
                                                            'not_contains' => 'Neobsahuje',
                                                            'filled' => 'Je vyplněno',
                                                            'blank' => 'Je prázdné',
                                                        ])
                                                        ->default('equals')
                                                        ->native(false)
                                                        ->live(),
                                                ]),
                                                TextInput::make('value')
                                                    ->label('Hodnota')
                                                    ->visible(fn (Get $get): bool => in_array((string) $get('operator'), ['equals', 'not_equals', 'contains', 'not_contains'], true)),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->defaultItems(0)
                                            ->addActionLabel('Přidat podmínku'),
                                    ])
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Přidat pole'),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Přidat sekci'),
            ]),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function getFieldTypeSettings(?string $type): array
    {
        if (! $type) {
            return [];
        }

        $registry = app(FieldTypeRegistry::class);

        if (! $registry->has($type)) {
            return [];
        }

        return $registry->get($type)->settingsSchema();
    }

    private static function hasFieldTypeSettings(?string $type): bool
    {
        return static::getFieldTypeSettings($type) !== [];
    }
}
