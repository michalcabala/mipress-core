<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\ColorPicker;
use Filament\Tables\Columns\ColorColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class ColorFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'color';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-palette';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Text;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return ColorPicker::make($handle)
            ->label($label)
            ->required($required);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return ColorColumn::make($handle)
            ->label($label);
    }
}
