<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\Hidden;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class HiddenFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'hidden';
    }

    public static function label(): string
    {
        return 'Skryté';
    }

    public static function icon(): string
    {
        return 'fal-eye-slash';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Presentation;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Hidden::make($handle);
    }
}
