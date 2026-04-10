<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryController extends Controller
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'general.homepage_page_id';

    private const LEGACY_HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

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
            $featuredEntries = Entry::query()
                ->with(['collection', 'featuredImage', 'revisions'])
                ->publiclyVisible()
                ->orderByDesc('published_at')
                ->limit(8)
                ->get()
                ->map(fn (Entry $entry): Entry => $entry->resolvePublicVersion())
                ->filter(fn (Entry $entry): bool => $entry->isPublished())
                ->take(4)
                ->values();

            return view('home', [
                'collections' => mipress_public_collections(),
                'featuredEntries' => $featuredEntries,
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
        $homepagePageId = Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY)
            ?? Setting::getValue(self::LEGACY_HOMEPAGE_PAGE_SETTING_KEY);

        if (! filled($homepagePageId)) {
            return null;
        }

        return Page::query()
            ->with(['blueprint', 'featuredImage'])
            ->publiclyVisible()
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
            ->publiclyVisible()
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

            $entry = $collection->entries()
                ->with(['collection', 'blueprint', 'featuredImage'])
                ->publiclyVisible()
                ->where('slug', $slug)
                ->first();

            if ($entry instanceof Entry) {
                return $entry;
            }

            $entry = $this->resolveEntryByApprovedSlug($collection, $slug);

            if ($entry instanceof Entry) {
                return $entry;
            }
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

        $page = Page::query()
            ->with(['blueprint', 'featuredImage'])
            ->publiclyVisible()
            ->where('slug', $slug)
            ->first();

        if ($page instanceof Page) {
            return $page;
        }

        return $this->resolvePageByApprovedSlug($slug);
    }

    private function renderEntry(Entry $entry): View
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

    private function renderPage(Page $page): View
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

    private function resolveContentType(string $filePath): string
    {
        return match (pathinfo($filePath, PATHINFO_EXTENSION)) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            default => (function () use ($filePath): string {
                $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($filePath);

                return is_string($mimeType) && $mimeType !== ''
                    ? $mimeType
                    : 'application/octet-stream';
            })(),
        };
    }

    private function resolveEntryByApprovedSlug(Collection $collection, string $slug): ?Entry
    {
        return $collection->entries()
            ->with(['collection', 'blueprint', 'featuredImage', 'revisions'])
            ->where('status', EntryStatus::InReview)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->latest('updated_at')
            ->lazy()
            ->first(function (Entry $entry) use ($slug): bool {
                $publicVersion = $entry->resolvePublicVersion();

                return $publicVersion->isPublished() && $publicVersion->slug === $slug;
            });
    }

    private function resolvePageByApprovedSlug(string $slug): ?Page
    {
        return Page::query()
            ->with(['blueprint', 'featuredImage', 'revisions'])
            ->where('status', EntryStatus::InReview)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->latest('updated_at')
            ->lazy()
            ->first(function (Page $page) use ($slug): bool {
                $publicVersion = $page->resolvePublicVersion();

                return $publicVersion->isPublished() && $publicVersion->slug === $slug;
            });
    }
}
