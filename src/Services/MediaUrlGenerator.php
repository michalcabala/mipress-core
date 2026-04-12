<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Media;

class MediaUrlGenerator
{
    public function media(?Media $media, string $variant = 'default'): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $conversion = $this->resolveConversionName($variant);

        if ($conversion !== null && $media->hasGeneratedConversion($conversion)) {
            return $media->getFullUrl($conversion);
        }

        return $media->getFullUrl();
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
        $storage = Storage::disk($disk ?: MediaConfig::disk());

        return $this->absolutizeUrl($storage->url($path));
    }

    private function resolveConversionName(string $variant): ?string
    {
        return MediaConfig::resolveVariantName($variant);
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
