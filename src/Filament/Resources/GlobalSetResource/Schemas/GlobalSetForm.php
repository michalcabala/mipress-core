<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GlobalSetForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základní informace')->schema([
                TextInput::make('title')
                    ->label('Název')
                    ->required()
                    ->maxLength(255),
                TextInput::make('handle')
                    ->label('Handle')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->regex('/^[a-z0-9_]+$/')
                    ->helperText('Pouze malá písmena, číslice a podtržítka.')
                    ->disabled(fn (?string $operation): bool => $operation === 'edit'),
            ]),
            Section::make('Data')->schema([
                KeyValue::make('data')
                    ->label('Klíč-hodnota')
                    ->keyLabel('Klíč')
                    ->valueLabel('Hodnota')
                    ->reorderable(),
            ]),
        ]);
    }
}
