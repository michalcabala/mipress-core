<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;

class TextFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'text';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-font';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Text;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return TextInput::make($handle)
            ->label($label)
            ->required($required)
            ->maxLength($config['maxLength'] ?? 255)
            ->placeholder($config['placeholder'] ?? null);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return TextColumn::make($handle)
            ->label($label)
            ->limit(50);
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('config.maxLength')
                ->label(static::translateSettingLabel('max_length'))
                ->numeric()
                ->default(255),
            TextInput::make('config.placeholder')
                ->label(static::translateSettingLabel('placeholder')),
        ];
    }
}
