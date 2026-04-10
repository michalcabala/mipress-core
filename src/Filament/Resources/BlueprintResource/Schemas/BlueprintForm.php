<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                                        ->searchable(),
                                    Select::make('required')
                                        ->label('Povinné')
                                        ->options([
                                            '0' => 'Ne',
                                            '1' => 'Ano',
                                        ])
                                        ->default('0'),
                                ]),
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
}
