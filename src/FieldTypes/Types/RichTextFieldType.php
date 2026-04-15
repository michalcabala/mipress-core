<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\RichEditor;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class RichTextFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'richtext';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-pen-nib';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Text;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return RichEditor::make($handle)
            ->label($label)
            ->required($required)
            ->columnSpanFull();
    }
}
