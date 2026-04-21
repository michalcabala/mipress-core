<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MiPress\Core\Services\PublicContentRenderer;
use MiPress\Core\Services\PublicContentRouteResolver;
use MiPress\Core\Services\PublicHomepageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntryController extends Controller
{
    public function __construct(
        private readonly PublicHomepageService $homepageService,
        private readonly PublicContentRouteResolver $routeResolver,
        private readonly PublicContentRenderer $contentRenderer,
    ) {}

    public function home(): View
    {
        return $this->homepageService->render();
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

        $archiveCollection = $this->routeResolver->resolveArchiveCollection($path);

        if ($archiveCollection !== null) {
            return $this->contentRenderer->renderArchive($archiveCollection);
        }

        $entry = $this->routeResolver->resolveEntryFromPath($path);

        if ($entry !== null) {
            return $this->contentRenderer->renderEntry($entry);
        }

        $page = $this->routeResolver->resolvePageFromPath($path);

        if ($page !== null) {
            return $this->contentRenderer->renderPage($page);
        }

        abort(404);
    }

    private function resolveThemeFilePath(string $theme, string $path): ?string
    {
        $themeRoot = resource_path('themes/'.$theme);
        $resolvedThemeRoot = realpath($themeRoot);

        if ($resolvedThemeRoot === false) {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', ltrim($path, '/'));

        if (! str_starts_with($normalizedPath, 'assets/')) {
            return null;
        }

        $assetsRoot = realpath($resolvedThemeRoot.DIRECTORY_SEPARATOR.'assets');

        if ($assetsRoot === false) {
            return null;
        }

        $candidatePath = realpath($resolvedThemeRoot.DIRECTORY_SEPARATOR.$normalizedPath);

        if ($candidatePath === false) {
            return null;
        }

        if (! str_starts_with($candidatePath, $assetsRoot.DIRECTORY_SEPARATOR)
            && $candidatePath !== $assetsRoot) {
            return null;
        }

        if (! is_file($candidatePath)) {
            return null;
        }

        return $candidatePath;
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
}
