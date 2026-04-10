<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class DateFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'date';
    }

    public static function label(): string
    {
        return 'Datum';
    }

    public static function icon(): string
    {
        return 'fal-calendar';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::DateTime;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return DatePicker::make($handle)
            ->label($label)
            ->required($required);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->date('j. n. Y')
            ->sortable();
    }
}
