<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\PreviewContentService;

class PreviewController extends Controller
{
    public function __construct(private readonly PreviewContentService $previewContentService) {}

    public function __invoke(string $entry): RedirectResponse|Response
    {
        $entry = Entry::query()->findOrFail($entry);

        return $this->previewContentService->renderEntryPreview($entry);
    }
}
