<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use MiPress\Core\Models\Entry;

class EntryTaxonomySyncService
{
    /**
     * @param  array<string, mixed>  $formState
     */
    public function syncFromFormState(Entry $entry, array $formState): void
    {
        $taxonomyIds = collect($formState)
            ->keys()
            ->filter(fn (string $key): bool => str_starts_with($key, 'taxonomy__'))
            ->map(fn (string $key): int => (int) str_replace('taxonomy__', '', $key))
            ->values()
            ->all();

        if ($taxonomyIds === []) {
            return;
        }

        $incomingTermIds = collect($formState)
            ->filter(fn ($value, string $key): bool => str_starts_with($key, 'taxonomy__'))
            ->flatten()
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $preservedTermIds = $entry->terms()
            ->whereNotIn('taxonomy_id', $taxonomyIds)
            ->pluck('terms.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $entry->terms()->sync(array_values(array_unique([
            ...$preservedTermIds,
            ...$incomingTermIds,
        ])));
    }
}
