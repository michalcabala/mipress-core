<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class DateTimeFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'datetime';
    }

    public static function label(): string
    {
        return 'Datum a čas';
    }

    public static function icon(): string
    {
        return 'fal-calendar-clock';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::DateTime;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return DateTimePicker::make($handle)
            ->label($label)
            ->required($required);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->dateTime('j. n. Y H:i')
            ->sortable();
    }
}
