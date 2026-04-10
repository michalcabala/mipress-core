<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class NumberFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'number';
    }

    public static function label(): string
    {
        return 'Číslo';
    }

    public static function icon(): string
    {
        return 'fal-input-numeric';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Numeric;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return TextInput::make($handle)
            ->label($label)
            ->required($required)
            ->numeric()
            ->minValue($config['min'] ?? null)
            ->maxValue($config['max'] ?? null);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->numeric();
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('config.min')
                ->label('Minimum')
                ->numeric(),
            TextInput::make('config.max')
                ->label('Maximum')
                ->numeric(),
        ];
    }
}
