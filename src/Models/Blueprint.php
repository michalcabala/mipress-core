<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use MiPress\Core\Database\Factories\BlueprintFactory;
use MiPress\Core\FieldTypes\FieldTypeRegistry;

class Blueprint extends Model
{
    use HasFactory;

    protected $table = 'blueprints';

    protected $fillable = [
        'name',
        'handle',
        'fields',
    ];

    protected $casts = [
        'fields' => 'array',
    ];

    protected $attributes = [
        'fields' => '[]',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $blueprint): void {
            $blueprint->fields = static::normalizeFieldsPayload($blueprint->fields);

            static::assertUniqueFieldHandles($blueprint->fields);
            static::assertKnownFieldTypes($blueprint->fields);
        });
    }

    protected static function newFactory(): BlueprintFactory
    {
        return BlueprintFactory::new();
    }

    /**
     * @param  mixed  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFieldsPayload(mixed $fields): array
    {
        if (! is_array($fields) || $fields === []) {
            return [];
        }

        $firstItem = $fields[0] ?? null;

        if (is_array($firstItem) && array_key_exists('section', $firstItem)) {
            return static::normalizeNestedSections($fields);
        }

        return static::normalizeFlatFields($fields);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeNestedSections(array $sections): array
    {
        $normalizedSections = [];

        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $sectionName = trim((string) ($section['section'] ?? ''));
            $sectionFields = static::normalizeFieldDefinitions($section['fields'] ?? []);

            if ($sectionFields === []) {
                continue;
            }

            $normalizedSection = $section;
            $normalizedSection['section'] = $sectionName !== '' ? $sectionName : 'Sekce '.($index + 1);
            $normalizedSection['fields'] = $sectionFields;

            $normalizedSections[] = $normalizedSection;
        }

        return $normalizedSections;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFlatFields(array $fields): array
    {
        return static::normalizeFieldDefinitions($fields);
    }

    /**
     * @param  mixed  $fieldDefinitions
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeFieldDefinitions(mixed $fieldDefinitions): array
    {
        if (! is_array($fieldDefinitions)) {
            return [];
        }

        $normalized = [];

        foreach ($fieldDefinitions as $index => $fieldDefinition) {
            if (! is_array($fieldDefinition)) {
                continue;
            }

            $handle = trim((string) ($fieldDefinition['handle'] ?? ''));

            if ($handle === '') {
                continue;
            }

            $normalizedField = $fieldDefinition;
            $normalizedField['handle'] = $handle;
            $normalizedField['label'] = trim((string) ($fieldDefinition['label'] ?? $handle));
            $normalizedField['type'] = trim((string) ($fieldDefinition['type'] ?? 'text')) ?: 'text';
            $normalizedField['required'] = static::normalizeBoolean($fieldDefinition['required'] ?? false);
            $normalizedField['show_in_table'] = static::normalizeBoolean($fieldDefinition['show_in_table'] ?? false);
            $normalizedField['searchable'] = static::normalizeBoolean($fieldDefinition['searchable'] ?? false);
            $normalizedField['sortable'] = static::normalizeBoolean($fieldDefinition['sortable'] ?? false);
            $normalizedField['order'] = is_numeric($fieldDefinition['order'] ?? null)
                ? (int) $fieldDefinition['order']
                : $index;
            $normalizedField['config'] = is_array($fieldDefinition['config'] ?? null)
                ? $fieldDefinition['config']
                : [];

            $normalized[] = $normalizedField;
        }

        return $normalized;
    }

    private static function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private static function assertUniqueFieldHandles(array $fields): void
    {
        $handles = collect(static::flattenFields($fields))
            ->map(static fn (array $field): string => strtolower((string) ($field['handle'] ?? '')))
            ->filter()
            ->values();

        $duplicates = $handles->duplicates()->unique()->values()->all();

        if ($duplicates === []) {
            return;
        }

        throw ValidationException::withMessages([
            'fields' => 'Handle pole musi byt v ramci jedne struktury unikatni. Duplicity: '.implode(', ', $duplicates).'.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private static function assertKnownFieldTypes(array $fields): void
    {
        if (! app()->bound(FieldTypeRegistry::class)) {
            return;
        }

        $registry = app(FieldTypeRegistry::class);

        $invalid = collect(static::flattenFields($fields))
            ->map(static fn (array $field): array => [
                'handle' => (string) ($field['handle'] ?? ''),
                'type' => (string) ($field['type'] ?? ''),
            ])
            ->filter(fn (array $item): bool => $item['type'] !== '' && ! $registry->has($item['type']))
            ->map(static fn (array $item): string => $item['type'].' ('.$item['handle'].')')
            ->values()
            ->all();

        if ($invalid === []) {
            return;
        }

        throw ValidationException::withMessages([
            'fields' => 'Nalezene neplatne typy poli: '.implode(', ', $invalid).'.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function flattenFields(array $fields): array
    {
        if ($fields === []) {
            return [];
        }

        $firstItem = $fields[0] ?? null;

        if (is_array($firstItem) && array_key_exists('section', $firstItem)) {
            return collect($fields)
                ->flatMap(static fn (array $section): array => is_array($section['fields'] ?? null) ? $section['fields'] : [])
                ->values()
                ->all();
        }

        return $fields;
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
