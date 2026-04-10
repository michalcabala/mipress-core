<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class SelectFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'select';
    }

    public static function label(): string
    {
        return 'Výběr';
    }

    public static function icon(): string
    {
        return 'fal-list-dropdown';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Selection;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Select::make($handle)
            ->label($label)
            ->required($required)
            ->options($config['options'] ?? [])
            ->multiple($config['multiple'] ?? false);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->badge();
    }

    public function toFilter(string $handle, string $label, array $config): mixed
    {
        return \Filament\Tables\Filters\SelectFilter::make($handle)
            ->label($label)
            ->options($config['options'] ?? []);
    }

    public function settingsSchema(): array
    {
        return [
            KeyValue::make('config.options')
                ->label('Možnosti (klíč → hodnota)'),
            Select::make('config.multiple')
                ->label('Vícenásobný výběr')
                ->options(['0' => 'Ne', '1' => 'Ano'])
                ->default('0'),
        ];
    }
}
