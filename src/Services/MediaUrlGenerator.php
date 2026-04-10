<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Config\GlideManager;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Models\Media;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class MediaUrlGenerator
{
    /**
     * @var array<string, array{curation?: string, glide: array<string, string>}>
     */
    private const VARIANTS = [
        'default' => [
            'glide' => [
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
        'avatar' => [
            'glide' => [
                'w' => '160',
                'h' => '160',
                'fit' => 'crop',
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
        'thumbnail' => [
            'curation' => 'thumbnail',
            'glide' => [
                'w' => '200',
                'h' => '200',
                'fit' => 'crop',
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
        'card' => [
            'curation' => 'medium',
            'glide' => [
                'w' => '720',
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
        'hero' => [
            'curation' => 'large',
            'glide' => [
                'w' => '1440',
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
        'og' => [
            'curation' => 'og',
            'glide' => [
                'w' => '1200',
                'h' => '630',
                'fit' => 'crop',
                'fm' => 'webp',
                'q' => '85',
            ],
        ],
    ];

    public function media(?Media $media, string $variant = 'default'): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        if (! Curator::isResizable($media->ext)) {
            return $this->absolutizeUrl($media->url);
        }

        $variantConfig = $this->getVariantConfig($variant);
        $curationKey = $variantConfig['curation'] ?? null;

        if ($curationKey !== null && $media->hasCuration($curationKey)) {
            return $this->resolveCurationUrl($media, $curationKey);
        }

        return $this->absolutizeUrl(app(GlideManager::class)->getUrl($media->path, $variantConfig['glide']));
    }

    public function path(?string $path, string $variant = 'default', string $disk = 'public'): ?string
    {
        $path = $this->normalizePath($path);

        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if ($disk !== 'public') {
            /** @var FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            return $this->absolutizeUrl($storage->url($path));
        }

        return $this->absolutizeUrl(app(GlideManager::class)->getUrl($path, $this->getVariantConfig($variant)['glide']));
    }

    /**
     * Return the stored URL for a curation directly, without re-processing through Glide.
     * Curations are already pre-processed images saved on disk — their URL is the canonical source.
     */
    private function resolveCurationUrl(Media $media, string $curationKey): ?string
    {
        $curation = $media->getCuration($curationKey);

        if (isset($curation['url']) && $curation['url'] !== '') {
            return $this->absolutizeUrl($curation['url']);
        }

        $disk = $curation['disk'] ?? 'public';
        $path = $curation['path'] ?? null;

        if ($path !== null && $path !== '') {
            /** @var FilesystemAdapter $storage */
            $storage = Storage::disk($disk);

            return $this->absolutizeUrl($storage->url(ltrim($path, '/')));
        }

        // Fallback: serve original via Glide without curation
        return $this->absolutizeUrl(app(GlideManager::class)->getUrl($media->path, $this->getVariantConfig('default')['glide']));
    }

    /**
     * @return array{curation?: string, glide: array<string, string>}
     */
    private function getVariantConfig(string $variant): array
    {
        return self::VARIANTS[$variant] ?? self::VARIANTS['default'];
    }

    private function normalizePath(?string $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        return $path === '' ? null : $path;
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
