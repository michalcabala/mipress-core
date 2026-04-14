<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\View\View;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

class PublicContentRenderer
{
    public function renderArchive(Collection $collection): View
    {
        $query = $collection->entries()
            ->with(['collection', 'featuredImage', 'revisions'])
            ->publiclyVisible();

        $entries = $collection
            ->applyPublicOrdering($query)
            ->paginate(9)
            ->withQueryString();

        $entries->setCollection(
            $entries->getCollection()
                ->map(fn (Entry $entry): Entry => $entry->resolvePublicVersion())
                ->filter(fn (Entry $entry): bool => $entry->isPublished())
                ->values(),
        );

        $featuredEntry = $entries->getCollection()->first();
        $viewHandle = 'collections.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'collections.archive';

        return view($viewName, [
            'collection' => $collection,
            'entries' => $entries,
            'featuredEntry' => $featuredEntry,
        ]);
    }

    public function renderEntry(Entry $entry): View
    {
        $entry = $entry->resolvePublicVersion();

        if (! $entry->isPublished()) {
            abort(404);
        }

        $entry->loadMissing(['collection', 'blueprint', 'featuredImage']);
        $collection = $entry->collection;

        if (! $collection instanceof Collection) {
            abort(404);
        }

        $viewHandle = 'entries.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'entries.page';
        $relatedEntries = $collection->entries()
            ->with(['collection', 'featuredImage', 'revisions'])
            ->publiclyVisible()
            ->whereKeyNot($entry->getKey())
            ->orderByDesc('published_at')
            ->limit(6)
            ->get()
            ->map(fn (Entry $relatedEntry): Entry => $relatedEntry->resolvePublicVersion())
            ->filter(fn (Entry $relatedEntry): bool => $relatedEntry->isPublished())
            ->take(3)
            ->values();

        return view($viewName, compact('entry', 'collection', 'relatedEntries'));
    }

    public function renderPage(Page $page): View
    {
        $page = $page->resolvePublicVersion();

        if (! $page->isPublished()) {
            abort(404);
        }

        $page->loadMissing(['blueprint', 'featuredImage']);

        $viewName = view()->exists('entries.page') ? 'entries.page' : 'welcome';

        return view($viewName, [
            'entry' => $page,
            'page' => $page,
            'collection' => null,
            'relatedEntries' => collect(),
        ]);
    }
}
