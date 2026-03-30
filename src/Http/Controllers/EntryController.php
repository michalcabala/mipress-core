<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryController extends Controller
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    public function home(): View
    {
        $page = $this->resolveHomepagePage();

        if ($page instanceof Page) {
            return $this->renderPage($page);
        }

        $entry = $this->resolveHomepageEntry();

        if ($entry instanceof Entry) {
            return $this->renderEntry($entry);
        }

        if (view()->exists('home')) {
            return view('home', [
                'collections' => mipress_public_collections(),
                'featuredEntries' => Entry::query()
                    ->with(['collection', 'featuredImage'])
                    ->published()
                    ->orderByDesc('published_at')
                    ->limit(4)
                    ->get(),
            ]);
        }

        return view('welcome');
    }

    public function asset(string $theme, string $path): BinaryFileResponse
    {
        $filePath = $this->resolveThemeFilePath($theme, $path);

        abort_if($filePath === null, 404);

        return response()->file($filePath, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => $this->resolveContentType($filePath),
        ]);
    }

    public function __invoke(Request $request): View
    {
        $path = '/'.ltrim($request->path(), '/');

        $archiveCollection = $this->resolveArchiveCollection($path);

        if ($archiveCollection instanceof Collection) {
            return $this->renderArchive($archiveCollection);
        }

        $entry = $this->resolveEntryFromPath($path);

        if ($entry instanceof Entry) {
            return $this->renderEntry($entry);
        }

        $page = $this->resolvePageFromPath($path);

        if ($page instanceof Page) {
            return $this->renderPage($page);
        }

        abort(404);
    }

    private function resolveHomepagePage(): ?Page
    {
        $homepagePageId = Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY);

        if (! filled($homepagePageId)) {
            return null;
        }

        return Page::query()
            ->with(['blueprint', 'featuredImage'])
            ->published()
            ->find($homepagePageId);
    }

    private function resolveHomepageEntry(): ?Entry
    {
        $homepageEntryId = Setting::getValue(self::LEGACY_HOMEPAGE_ENTRY_SETTING_KEY);

        if (! filled($homepageEntryId)) {
            return null;
        }

        return Entry::query()
            ->with(['collection', 'blueprint', 'featuredImage'])
            ->published()
            ->find($homepageEntryId);
    }

    private function resolveEntryFromPath(string $path): ?Entry
    {
        $collections = mipress_routable_collections();

        foreach ($collections as $collection) {
            $slug = $collection->resolveSlugFromPath($path);

            if (! filled($slug)) {
                continue;
            }

            return $collection->entries()
                ->with(['collection', 'blueprint', 'featuredImage'])
                ->published()
                ->where('slug', $slug)
                ->first();
        }

        return null;
    }

    private function resolveArchiveCollection(string $path): ?Collection
    {
        foreach (mipress_public_collections() as $collection) {
            if ($collection->isArchivePath($path)) {
                return $collection;
            }
        }

        return null;
    }

    private function resolvePageFromPath(string $path): ?Page
    {
        $slug = ltrim($path, '/');

        return Page::query()
            ->with(['blueprint', 'featuredImage'])
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    private function renderEntry(Entry $entry): View
    {
        $entry->loadMissing(['collection', 'blueprint', 'featuredImage']);
        $collection = $entry->collection;

        if (! $collection instanceof Collection) {
            abort(404);
        }

        $viewHandle = 'entries.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'entries.page';
        $relatedEntries = $collection->entries()
            ->with(['collection', 'featuredImage'])
            ->published()
            ->whereKeyNot($entry->getKey())
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view($viewName, compact('entry', 'collection', 'relatedEntries'));
    }

    private function renderPage(Page $page): View
    {
        $page->loadMissing(['blueprint', 'featuredImage']);

        $viewName = view()->exists('entries.page') ? 'entries.page' : 'welcome';

        return view($viewName, [
            'entry' => $page,
            'page' => $page,
            'collection' => null,
            'relatedEntries' => collect(),
        ]);
    }

    private function resolveThemeFilePath(string $theme, string $path): ?string
    {
        $themeRoot = resource_path('themes/'.$theme);
        $resolvedThemeRoot = realpath($themeRoot);

        if ($resolvedThemeRoot === false) {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));
        $candidatePath = realpath($resolvedThemeRoot.DIRECTORY_SEPARATOR.$normalizedPath);

        if ($candidatePath === false) {
            return null;
        }

        if (! str_starts_with($candidatePath, $resolvedThemeRoot.DIRECTORY_SEPARATOR)
            && $candidatePath !== $resolvedThemeRoot) {
            return null;
        }

        if (! is_file($candidatePath)) {
            return null;
        }

        return $candidatePath;
    }

    private function renderArchive(Collection $collection): View
    {
        $query = $collection->entries()
            ->with(['collection', 'featuredImage'])
            ->published();

        $entries = $collection
            ->applyPublicOrdering($query)
            ->paginate(9)
            ->withQueryString();

        $featuredEntry = $entries->getCollection()->first();
        $viewHandle = 'collections.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'collections.archive';

        return view($viewName, [
            'collection' => $collection,
            'entries' => $entries,
            'featuredEntry' => $featuredEntry,
        ]);
    }

    private function resolveContentType(string $filePath): string
    {
        return match (pathinfo($filePath, PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            default => mime_content_type($filePath) ?: 'application/octet-stream',
        };
    }
}
