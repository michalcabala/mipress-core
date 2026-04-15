<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes;

use MiPress\Core\FieldTypes\Contracts\FieldType;

abstract class AbstractFieldType implements FieldType
{
    protected static function translateTypeLabel(): string
    {
        return __('mipress::admin.field_types.types.'.static::key());
    }

    protected static function translateSettingLabel(string $key): string
    {
        return __('mipress::admin.field_types.settings.'.$key);
    }

    public function toTableColumn(string $handle, string $label, array $config): mixed
    {
        return null;
    }

    public function toFilter(string $handle, string $label, array $config): mixed
    {
        return null;
    }

    public function settingsSchema(): array
    {
        return [];
    }
}
