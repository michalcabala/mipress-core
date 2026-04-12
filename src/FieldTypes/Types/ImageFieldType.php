<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;
use MiPress\Core\Filament\Forms\Components\MediaPicker;

class ImageFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'image';
    }

    public static function label(): string
    {
        return 'Obrázek';
    }

    public static function icon(): string
    {
        return 'fal-image';
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
