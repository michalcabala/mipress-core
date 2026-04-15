<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\Checkbox;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class CheckboxFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'checkbox';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-square-check';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Boolean;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Checkbox::make($handle)
            ->label($label)
            ->required($required);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return IconColumn::make($handle)
            ->label($label)
            ->boolean();
    }

    public function toFilter(string $handle, string $label, array $config): mixed
    {
        return TernaryFilter::make($handle)
            ->label($label);
    }
}
