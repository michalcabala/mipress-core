<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\TagsInput;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class TagsFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'tags';
    }

    public static function label(): string
    {
        return 'Štítky';
    }

    public static function icon(): string
    {
        return 'fal-tags';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Selection;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return TagsInput::make($handle)
            ->label($label)
            ->required($required);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->badge()
            ->separator(',');
    }
}
