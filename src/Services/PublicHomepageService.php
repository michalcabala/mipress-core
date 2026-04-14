<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\View\View;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;

class PublicHomepageService
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'general.homepage_page_id';

    private const LEGACY_HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    public function __construct(private readonly PublicContentRenderer $contentRenderer) {}

    public function render(): View
    {
        $page = $this->resolveHomepagePage();

        if ($page instanceof Page) {
            return $this->contentRenderer->renderPage($page);
        }

        $entry = $this->resolveHomepageEntry();

        if ($entry instanceof Entry) {
            return $this->contentRenderer->renderEntry($entry);
        }

        return $this->renderFallbackHomepage();
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

    private function renderFallbackHomepage(): View
    {
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
}
