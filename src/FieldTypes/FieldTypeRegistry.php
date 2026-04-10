<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes;

use MiPress\Core\FieldTypes\Contracts\FieldType;

class FieldTypeRegistry
{
    /** @var array<string, FieldType> */
    private array $types = [];

    /**
     * @param  class-string<FieldType>  $fieldTypeClass
     */
    public function register(string $fieldTypeClass): void
    {
        $instance = new $fieldTypeClass;
        $this->types[$fieldTypeClass::key()] = $instance;
    }

    public function get(string $key): FieldType
    {
        return $this->types[$key] ?? $this->types['text']
            ?? throw new \InvalidArgumentException("Field type [{$key}] is not registered.");
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @return array<string, FieldType>
     */
    public function all(): array
    {
        return $this->types;
    }

    /**
     * Return grouped options for Select dropdowns in BlueprintForm.
     *
     * @return array<string, array<string, string>>
     */
    public function groupedOptions(): array
    {
        $grouped = [];

        foreach ($this->types as $type) {
            $category = $type::category();
            $grouped[$category->label()][$type::key()] = $type::label();
        }

        return $grouped;
    }

    /**
     * Flat key => label options.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $options = [];

        foreach ($this->types as $type) {
            $options[$type::key()] = $type::label();
        }

        return $options;
    }
}
