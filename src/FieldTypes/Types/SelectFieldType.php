<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
        return static::translateTypeLabel();
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
        return SelectFilter::make($handle)
            ->label($label)
            ->options($config['options'] ?? []);
    }

    public function settingsSchema(): array
    {
        return [
            KeyValue::make('config.options')
                ->label(static::translateSettingLabel('options_key_value')),
            Select::make('config.multiple')
                ->label(static::translateSettingLabel('multiple'))
                ->options([
                    '0' => __('mipress::admin.common.no'),
                    '1' => __('mipress::admin.common.yes'),
                ])
                ->default('0'),
        ];
    }
}
