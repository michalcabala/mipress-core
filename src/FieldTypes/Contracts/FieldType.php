<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes\Contracts;

use MiPress\Core\FieldTypes\FieldCategory;

interface FieldType
{
    public static function key(): string;

    public static function label(): string;

    public static function icon(): string;

    public static function category(): FieldCategory;

    /**
     * Build a Filament form component from the field definition.
     *
     * @param  array<string, mixed>  $config
     */
    public function toFormComponent(string $handle, string $label, bool $required, array $config): mixed;

    /**
     * Build a Filament table column, or null if this type cannot be displayed in a table.
     *
     * @param  array<string, mixed>  $config
     */
    public function toTableColumn(string $handle, string $label, array $config): mixed;

    /**
     * Build a Filament table filter, or null if this type has no meaningful filter.
     *
     * @param  array<string, mixed>  $config
     */
    public function toFilter(string $handle, string $label, array $config): mixed;

    /**
     * Extra configuration fields shown in the Blueprint editor for this field type.
     *
     * @return array<int, mixed>
     */
    public function settingsSchema(): array;
}
