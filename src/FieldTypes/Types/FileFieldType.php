<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;
use MiPress\Core\Filament\Forms\Components\MediaPicker;

class FileFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'file';
    }

    public static function label(): string
    {
        return 'Soubor';
    }

    public static function icon(): string
    {
        return 'fal-file';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Media;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return MediaPicker::make($handle)
            ->label($label)
            ->required($required);
    }
}
