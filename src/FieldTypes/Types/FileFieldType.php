<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

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
        return CuratorPicker::make($handle)
            ->label($label)
            ->required($required);
    }
}
