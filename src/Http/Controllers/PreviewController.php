<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class PreviewController extends Controller
{
    public function __invoke(string $entry): RedirectResponse|Response
    {
        $entry = Entry::query()->findOrFail($entry);

        if ($entry->status === EntryStatus::Published && filled($entry->getPublicUrl())) {
            return redirect($entry->getPublicUrl());
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

        $rendered = view($viewName, [
            'entry' => $entry,
            'collection' => $collection,
            'relatedEntries' => $relatedEntries,
            'isPreview' => true,
        ])->render();

        $previewBanner = '<div style="position:sticky;top:0;z-index:9999;background:#fef3c7;color:#78350f;padding:10px 16px;font-weight:600;border-bottom:1px solid #fcd34d;">'
            .'Náhled obsahu (nepublikovaná verze)'
            .'</div>';

        return response($previewBanner.$rendered);
    }
}
