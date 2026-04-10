<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class ToggleFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'toggle';
    }

    public static function label(): string
    {
        return 'Přepínač';
    }

    public static function icon(): string
    {
        return 'fal-toggle-on';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Boolean;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Toggle::make($handle)
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
