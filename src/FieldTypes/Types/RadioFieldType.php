<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class RadioFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'radio';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-circle-dot';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Selection;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Radio::make($handle)
            ->label($label)
            ->required($required)
            ->options($config['options'] ?? []);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->badge();
    }

    public function settingsSchema(): array
    {
        return [
            KeyValue::make('config.options')
                ->label(static::translateSettingLabel('options_key_value')),
        ];
    }
}
