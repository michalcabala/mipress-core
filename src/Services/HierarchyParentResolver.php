<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Validation\ValidationException;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

class HierarchyParentResolver
{
    public function resolvePageParentForCreate(mixed $parentId): ?int
    {
        if (! is_numeric($parentId)) {
            return null;
        }

        return Page::query()
            ->whereKey((int) $parentId)
            ->value('id');
    }

    public function resolvePageParentForEdit(Page $record, mixed $parentId): ?int
    {
        $resolvedParentId = $this->resolvePageParentForCreate($parentId);

        if (! is_numeric($resolvedParentId)) {
            return null;
        }

        $resolvedParentId = (int) $resolvedParentId;

        if ($resolvedParentId === (int) $record->getKey()) {
            return null;
        }

        if ($this->wouldCreatePageCycle($record, $resolvedParentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Nelze vybrat podřízenou stránku jako nadřazenou.',
            ]);
        }

        return $resolvedParentId;
    }

    public function resolveEntryParentForCreate(?Collection $collection, mixed $parentId): ?int
    {
        if (! $collection?->hierarchical || ! is_numeric($parentId)) {
            return null;
        }

        return Entry::query()
            ->where('collection_id', $collection->id)
            ->whereKey((int) $parentId)
            ->value('id');
    }

    public function resolveEntryParentForEdit(Entry $record, mixed $parentId): ?int
    {
        if (! $record->collection?->hierarchical) {
            return null;
        }

        $resolvedParentId = Entry::query()
            ->where('collection_id', $record->collection_id)
            ->whereKey((int) $parentId)
            ->value('id');

        if (! is_numeric($resolvedParentId)) {
            return null;
        }

        $resolvedParentId = (int) $resolvedParentId;

        if ($resolvedParentId === (int) $record->getKey()) {
            return null;
        }

        if ($this->wouldCreateEntryCycle($record, $resolvedParentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Nelze vybrat podřízenou položku jako nadřazenou.',
            ]);
        }

        return $resolvedParentId;
    }

    private function wouldCreatePageCycle(Page $record, int $candidateParentId): bool
    {
        $currentId = $candidateParentId;
        $visited = [];

        while ($currentId > 0) {
            if ($currentId === (int) $record->getKey()) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;

            $parentId = Page::query()
                ->whereKey($currentId)
                ->value('parent_id');

            if (! is_numeric($parentId)) {
                return false;
            }

            $currentId = (int) $parentId;
        }

        return false;
    }

    private function wouldCreateEntryCycle(Entry $record, int $candidateParentId): bool
    {
        $currentId = $candidateParentId;
        $visited = [];

        while ($currentId > 0) {
            if ($currentId === (int) $record->getKey()) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;

            $parentId = Entry::query()
                ->where('collection_id', $record->collection_id)
                ->whereKey($currentId)
                ->value('parent_id');

            if (! is_numeric($parentId)) {
                return false;
            }

            $currentId = (int) $parentId;
        }

        return false;
    }
}
