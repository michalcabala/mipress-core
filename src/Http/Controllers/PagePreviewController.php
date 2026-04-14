<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\PreviewContentService;

class PagePreviewController extends Controller
{
    public function __construct(private readonly PreviewContentService $previewContentService) {}

    public function __invoke(string $page): RedirectResponse|Response
    {
        $page = Page::query()->findOrFail($page);

        return $this->previewContentService->renderPagePreview($page);
    }
}
