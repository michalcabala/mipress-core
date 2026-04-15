<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\MarkdownEditor;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class MarkdownFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'markdown';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-hashtag';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Text;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return MarkdownEditor::make($handle)
            ->label($label)
            ->required($required)
            ->columnSpanFull();
    }
}
