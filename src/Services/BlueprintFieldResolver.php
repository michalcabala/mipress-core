<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

        $component = $type->toFormComponent($handle, $label, $required, $config);

        return static::applyVisibilityConditions($component, $config);
    }

    /**
     * Evaluate whether a field should be visible for a given data payload.
     *
     * @param  array<string, mixed>  $fieldDefinition
     * @param  array<string, mixed>  $data
     */
    public static function shouldDisplayField(array $fieldDefinition, array $data): bool
    {
        $config = $fieldDefinition['config'] ?? [];

        if (! is_array($config)) {
            return true;
        }

        $conditions = static::normalizeVisibilityConditions($config['visibility_conditions'] ?? null);

        if ($conditions === []) {
            return true;
        }

        $mode = static::normalizeVisibilityMode($config['visibility_mode'] ?? null);

        return static::evaluateVisibilityConditions(
            conditions: $conditions,
            mode: $mode,
            valueResolver: static fn (string $field): mixed => data_get($data, $field),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function applyVisibilityConditions(mixed $component, array $config): mixed
    {
        if (! is_object($component) || ! method_exists($component, 'visible')) {
            return $component;
        }

        $conditions = static::normalizeVisibilityConditions($config['visibility_conditions'] ?? null);

        if ($conditions === []) {
            return $component;
        }

        $mode = static::normalizeVisibilityMode($config['visibility_mode'] ?? null);

        return $component->visible(fn (Get $get): bool => static::evaluateVisibilityConditions(
            conditions: $conditions,
            mode: $mode,
            valueResolver: static fn (string $field): mixed => $get($field),
        ));
    }

    /**
     * @param  array<int, array{field: string, operator: string, value?: mixed}>  $conditions
     * @param  \Closure(string): mixed  $valueResolver
     */
    private static function evaluateVisibilityConditions(array $conditions, string $mode, \Closure $valueResolver): bool
    {
        if ($conditions === []) {
            return true;
        }

        $results = collect($conditions)
            ->map(static fn (array $condition): bool => static::evaluateVisibilityCondition(
                value: $valueResolver($condition['field']),
                condition: $condition,
            ))
            ->values();

        if ($mode === 'any') {
            return $results->contains(true);
        }

        return ! $results->contains(false);
    }

    /**
     * @param  array{field: string, operator: string, value?: mixed}  $condition
     */
    private static function evaluateVisibilityCondition(mixed $value, array $condition): bool
    {
        $operator = $condition['operator'] ?? 'equals';
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'filled' => filled($value),
            'blank' => blank($value),
            'contains' => static::valueContains($value, $expected),
            'not_contains' => ! static::valueContains($value, $expected),
            'not_equals' => ! static::valueEquals($value, $expected),
            default => static::valueEquals($value, $expected),
        };
    }

    private static function valueContains(mixed $value, mixed $expected): bool
    {
        $normalizedExpected = static::normalizeVisibilityConditionValue($expected);

        if (is_array($value)) {
            $normalizedValues = collect($value)
                ->map(static fn (mixed $item): mixed => static::normalizeVisibilityConditionValue($item))
                ->values()
                ->all();

            return in_array($normalizedExpected, $normalizedValues, true);
        }

        if (blank($value) || blank($normalizedExpected)) {
            return false;
        }

        return str_contains(
            mb_strtolower((string) static::normalizeVisibilityConditionValue($value)),
            mb_strtolower((string) $normalizedExpected),
        );
    }

    private static function valueEquals(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            return static::valueContains($value, $expected);
        }

        $normalizedValue = static::normalizeVisibilityConditionValue($value);
        $normalizedExpected = static::normalizeVisibilityConditionValue($expected);

        if (is_bool($normalizedValue) || is_bool($normalizedExpected)) {
            return (bool) $normalizedValue === (bool) $normalizedExpected;
        }

        if ($normalizedValue === null || $normalizedExpected === null) {
            return $normalizedValue === $normalizedExpected;
        }

        return (string) $normalizedValue === (string) $normalizedExpected;
    }

    private static function normalizeVisibilityConditionValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = trim($value);

            return match (mb_strtolower($normalized)) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $normalized,
            };
        }

        return $value;
    }

    private static function normalizeVisibilityMode(mixed $mode): string
    {
        return mb_strtolower((string) $mode) === 'any' ? 'any' : 'all';
    }

    /**
     * @return array<int, array{field: string, operator: string, value?: mixed}>
     */
    private static function normalizeVisibilityConditions(mixed $conditions): array
    {
        if (! is_array($conditions) || $conditions === []) {
            return [];
        }

        $supportedOperators = ['equals', 'not_equals', 'contains', 'not_contains', 'filled', 'blank'];

        return collect($conditions)
            ->filter(fn (mixed $condition): bool => is_array($condition))
            ->map(function (array $condition) use ($supportedOperators): ?array {
                $field = trim((string) ($condition['field'] ?? ''));

                if ($field === '') {
                    return null;
                }

                $operator = mb_strtolower((string) ($condition['operator'] ?? 'equals'));

                if (! in_array($operator, $supportedOperators, true)) {
                    $operator = 'equals';
                }

                $normalized = [
                    'field' => $field,
                    'operator' => $operator,
                ];

                if (in_array($operator, ['equals', 'not_equals', 'contains', 'not_contains'], true)) {
                    $normalized['value'] = static::normalizeVisibilityConditionValue($condition['value'] ?? null);
                }

                return $normalized;
            })
            ->filter(fn (?array $condition): bool => $condition !== null)
            ->values()
            ->all();
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
            ->filter(fn (array $fieldDef): bool => (bool) ($fieldDef['show_in_table'] ?? false))
            ->map(function (array $fieldDef) use ($registry): mixed {
                $handle = $fieldDef['handle'] ?? null;
                $label = $fieldDef['label'] ?? $handle;
                $config = $fieldDef['config'] ?? [];
                $typeKey = $fieldDef['type'] ?? 'text';
                $isSearchable = (bool) ($fieldDef['searchable'] ?? false);
                $isSortable = (bool) ($fieldDef['sortable'] ?? false);

                if (! $handle) {
                    return null;
                }

                $type = $registry->get($typeKey);
                $column = $type->toTableColumn("data.{$handle}", $label, $config);

                if ($column === null) {
                    return null;
                }

                return $column
                    ->searchable($isSearchable)
                    ->sortable($isSortable)
                    ->toggleable();
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
