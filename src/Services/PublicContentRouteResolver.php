<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

class PublicContentRouteResolver
{
    public function resolveArchiveCollection(string $path): ?Collection
    {
        foreach (mipress_public_collections() as $collection) {
            if ($collection->isArchivePath($path)) {
                return $collection;
            }
        }

        return null;
    }

    public function resolveEntryFromPath(string $path): ?Entry
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

    public function resolvePageFromPath(string $path): ?Page
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

    private function resolveEntryByApprovedSlug(Collection $collection, string $slug): ?Entry
    {
        return $collection->entries()
            ->with(['collection', 'blueprint', 'featuredImage', 'revisions'])
            ->where('status', ContentStatus::InReview)
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
            ->where('status', ContentStatus::InReview)
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
