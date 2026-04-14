<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

class PreviewContentService
{
    public function renderEntryPreview(Entry $entry): RedirectResponse|Response
    {
        $redirectResponse = $this->redirectToPublicUrlIfPublished($entry);

        if ($redirectResponse instanceof RedirectResponse) {
            return $redirectResponse;
        }

        $entry->loadMissing(['collection', 'blueprint', 'featuredImage']);
        $collection = $entry->collection;

        if (! $collection instanceof Collection) {
            abort(404);
        }

        $viewHandle = 'entries.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'entries.page';

        $relatedEntries = $collection->entries()
            ->with(['collection', 'featuredImage'])
            ->whereKeyNot($entry->getKey())
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return $this->responseWithBanner(
            view($viewName, [
                'entry' => $entry,
                'collection' => $collection,
                'relatedEntries' => $relatedEntries,
                'isPreview' => true,
            ])->render(),
            'Náhled obsahu (nepublikovaná verze)',
        );
    }

    public function renderPagePreview(Page $page): RedirectResponse|Response
    {
        $redirectResponse = $this->redirectToPublicUrlIfPublished($page);

        if ($redirectResponse instanceof RedirectResponse) {
            return $redirectResponse;
        }

        $page->loadMissing(['blueprint', 'featuredImage']);

        $viewName = view()->exists('entries.page') ? 'entries.page' : 'welcome';

        return $this->responseWithBanner(
            view($viewName, [
                'entry' => $page,
                'page' => $page,
                'collection' => null,
                'relatedEntries' => collect(),
                'isPreview' => true,
            ])->render(),
            'Náhled stránky (nepublikovaná verze)',
        );
    }

    /**
     * @param  Entry|Page  $record
     */
    private function redirectToPublicUrlIfPublished(Entry|Page $record): ?RedirectResponse
    {
        if ($record->status !== EntryStatus::Published || ! filled($record->getPublicUrl())) {
            return null;
        }

        return redirect($record->getPublicUrl());
    }

    private function responseWithBanner(string $renderedView, string $bannerText): Response
    {
        $previewBanner = '<div style="position:sticky;top:0;z-index:9999;background:#fef3c7;color:#78350f;padding:10px 16px;font-weight:600;border-bottom:1px solid #fcd34d;">'
            .$bannerText
            .'</div>';

        return response($previewBanner.$renderedView);
    }
}