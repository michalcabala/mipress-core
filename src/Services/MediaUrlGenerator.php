<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Models\Media;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class MediaUrlGenerator
{
    /**
     * Get the URL for a Curator media record.
     *
     * The $variant parameter is accepted for backward compatibility
     * but curations are resolved by key from the media's curations JSON.
     */
    public function media(?Media $media, string $variant = 'default'): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        if ($variant !== 'default') {
            $curationUrl = $this->resolveCurationUrl($media, $variant);

            if ($curationUrl !== null) {
                return $curationUrl;
            }
        }

        return $media->url;
    }

    public function path(?string $path, string $variant = 'default', ?string $disk = null): ?string
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk ?: config('curator.default_disk', 'local_uploads'));

        return $this->absolutizeUrl($storage->url($path));
    }

    private function resolveCurationUrl(Media $media, string $variant): ?string
    {
        $curations = $media->curations;

        if (! is_array($curations)) {
            return null;
        }

        foreach ($curations as $item) {
            $curation = $item['curation'] ?? $item;

            if (isset($curation['key']) && $curation['key'] === $variant && isset($curation['url'])) {
                return $curation['url'];
            }
        }

        return null;
    }

    private function normalizePath(?string $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        return $path === '' ? null : ltrim($path, '/');
    }

    private function absolutizeUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, 'data:')) {
            return $url;
        }

        return url($url);
    }
}
