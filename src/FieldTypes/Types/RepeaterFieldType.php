<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Types;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use MiPress\Core\FieldTypes\AbstractFieldType;
use MiPress\Core\FieldTypes\FieldCategory;
use MiPress\Core\FieldTypes\FieldTypeRegistry;

class RepeaterFieldType extends AbstractFieldType
{
    public static function key(): string
    {
        return 'repeater';
    }

    public static function label(): string
    {
        return static::translateTypeLabel();
    }

    public static function icon(): string
    {
        return 'fal-layer-group';
    }

    public static function category(): FieldCategory
    {
        return FieldCategory::Structured;
    }

    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed
    {
        return Repeater::make($handle)
            ->label($label)
            ->required($required)
            ->schema(static::resolveSubFields($config))
            ->addActionLabel(static::translateSettingLabel('add_item'));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, mixed>
     */
    private static function resolveSubFields(array $config): array
    {
        $fieldDefinitions = $config['fields'] ?? null;

        if (! is_array($fieldDefinitions) || $fieldDefinitions === []) {
            return [
                TextInput::make('value')->label(static::translateSettingLabel('value')),
            ];
        }

        $registry = app(FieldTypeRegistry::class);
        $components = [];

        foreach ($fieldDefinitions as $fieldDefinition) {
            if (! is_array($fieldDefinition)) {
                continue;
            }

            $subHandle = $fieldDefinition['handle'] ?? null;
            $subLabel = $fieldDefinition['label'] ?? $subHandle;
            $subRequired = (bool) ($fieldDefinition['required'] ?? false);
            $subConfig = $fieldDefinition['config'] ?? [];
            $subType = $fieldDefinition['type'] ?? 'text';

            if (! $subHandle) {
                continue;
            }

            $type = $registry->get($subType);
            $component = $type->toFormComponent($subHandle, $subLabel, $subRequired, $subConfig);

            if ($component !== null) {
                $components[] = $component;
            }
        }

        return $components !== []
            ? $components
            : [TextInput::make('value')->label(static::translateSettingLabel('value'))];
    }
}
