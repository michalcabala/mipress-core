<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes;

use MiPress\Core\FieldTypes\Contracts\FieldType;

abstract class AbstractFieldType implements FieldType
{
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
