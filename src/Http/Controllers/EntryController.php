<?php

declare(strict_types=1);

namespace MiPress\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use MiPress\Core\Models\Collection;

class EntryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $path = '/'.ltrim($request->path(), '/');

        $collection = Collection::where('slugs', true)->get()
            ->first(fn (Collection $col) => (bool) preg_match($this->routeToRegex($col->route), $path));

        if (! $collection) {
            abort(404);
        }

        $slug = $this->extractSlug($path, $collection->route);

        $entry = $collection->entries()
            ->with(['blueprint', 'featuredImage'])
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $viewHandle = 'entries.'.$collection->handle;
        $viewName = view()->exists($viewHandle) ? $viewHandle : 'entries.page';

        return view($viewName, compact('entry', 'collection'));
    }

    private function routeToRegex(string $route): string
    {
        $parts = preg_split('#\{[^}]+\}#', $route) ?: [];
        $quoted = array_map(fn (string $p) => preg_quote($p, '#'), $parts);

        return '#^'.implode('[^/]+', $quoted).'$#';
    }

    private function extractSlug(string $path, string $routeTemplate): string
    {
        $parts = preg_split('#\{[^}]+\}#', $routeTemplate) ?: [];
        $quoted = array_map(fn (string $p) => preg_quote($p, '#'), $parts);
        $regex = '#^'.implode('([^/]+)', $quoted).'$#';

        if (preg_match($regex, $path, $matches)) {
            return $matches[1] ?? '';
        }

        return '';
    }
}
