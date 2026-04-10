<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Page;

class PagePreviewController extends Controller
{
    public function __invoke(string $page): RedirectResponse|Response
    {
        $page = Page::query()->findOrFail($page);

        if ($page->status === EntryStatus::Published && filled($page->getPublicUrl())) {
            return redirect($page->getPublicUrl());
        }

        $page->loadMissing(['blueprint', 'featuredImage']);

        $viewName = view()->exists('entries.page') ? 'entries.page' : 'welcome';

        $rendered = view($viewName, [
            'entry' => $page,
            'page' => $page,
            'collection' => null,
            'relatedEntries' => collect(),
            'isPreview' => true,
        ])->render();

        $previewBanner = '<div style="position:sticky;top:0;z-index:9999;background:#fef3c7;color:#78350f;padding:10px 16px;font-weight:600;border-bottom:1px solid #fcd34d;">'
            .'Náhled stránky (nepublikovaná verze)'
            .'</div>';

        return response($previewBanner.$rendered);
    }
}
