<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class TextareaFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'textarea';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-align-left';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Text;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Textarea::make($handle)
            ->label($label)
            ->required($required)
            ->rows($config['rows'] ?? 4);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->limit(80)
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('config.rows')
                ->label(static::translateSettingLabel('rows'))
                ->numeric()
                ->default(4),
        ];
    }
}
