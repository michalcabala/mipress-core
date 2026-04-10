<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Filament\Schemas\Components\Section;
use MiPress\Core\FieldTypes\FieldTypeRegistry;

class BlueprintFieldResolver
{
    /**
     * Resolve a single Blueprint field definition into a Filament form component.
     */
    public static function resolve(array $fieldDefinition): mixed
    {
        $handle = $fieldDefinition['handle'] ?? null;
        $label = $fieldDefinition['label'] ?? $handle;
        $required = (bool) ($fieldDefinition['required'] ?? false);
        $config = $fieldDefinition['config'] ?? [];

        if (! $handle) {
            return null;
        }

        $typeKey = $fieldDefinition['type'] ?? 'text';
        $registry = app(FieldTypeRegistry::class);
        $type = $registry->get($typeKey);

        return $type->toFormComponent($handle, $label, $required, $config);
    }

    /**
     * Resolve all Blueprint fields into ordered Filament Section components.
     * Handles both flat field arrays and nested section→fields structures.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, Section>
     */
    public static function resolveAll(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        // Detect structure: flat field array vs nested section→fields array
        $firstItem = $fields[0] ?? [];

        if (isset($firstItem['section'])) {
            return static::resolveNestedSections($fields);
        }

        return static::resolveFlatFields($fields);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, Section>
     */
    protected static function resolveNestedSections(array $sections): array
    {
        $result = [];

        foreach ($sections as $sectionDef) {
            $sectionFields = [];

            foreach ($sectionDef['fields'] ?? [] as $fieldDef) {
                $component = static::resolve($fieldDef);

                if ($component !== null) {
                    $sectionFields[] = $component;
                }
            }

            if (! empty($sectionFields)) {
                $result[] = Section::make($sectionDef['section'] ?? 'Pole')
                    ->statePath('data')
                    ->schema($sectionFields);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, Section>
     */
    protected static function resolveFlatFields(array $fields): array
    {
        $sorted = collect($fields)
            ->sortBy(fn (array $f): int => (int) ($f['order'] ?? 0))
            ->values()
            ->all();

        $components = [];

        foreach ($sorted as $fieldDef) {
            $component = static::resolve($fieldDef);

            if ($component !== null) {
                $components[] = $component;
            }
        }

        if (empty($components)) {
            return [];
        }

        return [
            Section::make('Pole')
                ->statePath('data')
                ->schema($components),
        ];
    }

    /**
     * Build Filament table columns from Blueprint field definitions.
     * Only fields whose FieldType returns a non-null toTableColumn() are included.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, mixed>
     */
    public static function resolveTableColumns(array $fields): array
    {
        $registry = app(FieldTypeRegistry::class);

        return collect(static::flattenFields($fields))
            ->map(function (array $fieldDef) use ($registry): mixed {
                $handle = $fieldDef['handle'] ?? null;
                $label = $fieldDef['label'] ?? $handle;
                $config = $fieldDef['config'] ?? [];
                $typeKey = $fieldDef['type'] ?? 'text';

                if (! $handle) {
                    return null;
                }

                $type = $registry->get($typeKey);
                $column = $type->toTableColumn("data.{$handle}", $label, $config);

                return $column?->toggleable();
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Build Filament table filters from Blueprint field definitions.
     * Only fields whose FieldType returns a non-null toFilter() are included.
     * Filters query the JSON `data` column using Eloquent's `->` JSON syntax.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, mixed>
     */
    public static function resolveFilters(array $fields): array
    {
        $registry = app(FieldTypeRegistry::class);

        return collect(static::flattenFields($fields))
            ->map(function (array $fieldDef) use ($registry): mixed {
                $handle = $fieldDef['handle'] ?? null;
                $label = $fieldDef['label'] ?? $handle;
                $config = $fieldDef['config'] ?? [];
                $typeKey = $fieldDef['type'] ?? 'text';

                if (! $handle) {
                    return null;
                }

                $type = $registry->get($typeKey);
                $filter = $type->toFilter($handle, $label, $config);

                if ($filter === null) {
                    return null;
                }

                // Prefix filter name to avoid naming collisions, and set attribute to JSON path
                return $filter
                    ->attribute("data->{$handle}");
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Flatten nested section→fields structure into a single flat list of field definitions.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    protected static function flattenFields(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $firstItem = $fields[0] ?? [];

        if (isset($firstItem['section'])) {
            return collect($fields)
                ->flatMap(fn (array $section): array => $section['fields'] ?? [])
                ->values()
                ->all();
        }

        return $fields;
    }
}
