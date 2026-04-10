<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\KeyValue;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class KeyValueFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'keyvalue';
    }

    public static function label(): string
    {
        return 'Klíč–hodnota';
    }

    public static function icon(): string
    {
        return 'fal-list';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Structured;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return KeyValue::make($handle)
            ->label($label)
            ->required($required);
    }
}
