<?php

declare(strict_types=1);

use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Media;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\MediaUrlGenerator;
use MiPress\Core\Services\SeoResolver;
use MiPress\Core\Services\SettingsManager;
use MiPress\Core\Theme\ThemeManager;

if (! function_exists('theme_asset')) {
    function theme_asset(string $path, ?string $slug = null): string
    {
        $slug ??= app(ThemeManager::class)->getActive();

        return theme_file('assets/'.ltrim($path, '/'), $slug);
    }
}

if (! function_exists('theme_file')) {
    function theme_file(string $path, ?string $slug = null): string
    {
        $slug ??= app(ThemeManager::class)->getActive();
        $encodedPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            array_filter(explode('/', str_replace('\\', '/', ltrim($path, '/'))), 'strlen'),
        ));

        return route('mipress.theme.asset', [
            'theme' => $slug,
            'path' => $encodedPath,
        ], false);
    }
}

if (! function_exists('mipress_routable_collections')) {
    /**
     * @return SupportCollection<int, Collection>
     */
    function mipress_routable_collections(): SupportCollection
    {
        try {
            return Collection::query()
                ->ordered()
                ->where('slugs', true)
                ->whereNotNull('route')
                ->get()
                ->values();
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve routable miPress collections.', [
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }
    }
}

if (! function_exists('mipress_public_collections')) {
    /**
     * @return SupportCollection<int, Collection>
     */
    function mipress_public_collections(): SupportCollection
    {
        return mipress_routable_collections()
            ->filter(fn (Collection $collection): bool => filled($collection->getArchivePath()))
            ->values();
    }
}

if (! function_exists('mipress_collection_archive_path')) {
    function mipress_collection_archive_path(?Collection $collection): ?string
    {
        return $collection?->getArchivePath();
    }
}

if (! function_exists('mipress_entry_url')) {
    function mipress_entry_url(Entry|Page $entry): ?string
    {
        return $entry->getPublicUrl();
    }
}

if (! function_exists('mipress_media_url')) {
    function mipress_media_url(?Media $media, string $variant = 'default'): ?string
    {
        return app(MediaUrlGenerator::class)->media($media, $variant);
    }
}

if (! function_exists('mipress_media_path_url')) {
    function mipress_media_path_url(?string $path, string $variant = 'default', ?string $disk = 'public'): ?string
    {
        return app(MediaUrlGenerator::class)->path($path, $variant, $disk);
    }
}

if (! function_exists('global_set')) {
    function global_set(string $expression, mixed $default = null): mixed
    {
        if (! str_contains($expression, '.')) {
            return settings($expression, default: $default);
        }

        [$handle, $key] = explode('.', $expression, 2);

        return settings($handle, $key, $default);
    }
}

if (! function_exists('settings')) {
    function settings(string $handle, ?string $key = null, mixed $default = null): mixed
    {
        $manager = app(SettingsManager::class);

        return $manager->get($handle, $key, $default);
    }
}

if (! function_exists('mipress_seo')) {
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    function mipress_seo(array $context = []): array
    {
        return app(SeoResolver::class)->resolve($context);
    }
}
