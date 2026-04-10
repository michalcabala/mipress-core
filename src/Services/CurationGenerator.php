<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Glide\Api\Api;

class CurationGenerator
{
    private const CURATION_EXTENSION = 'webp';

    private const RASTER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const UPLOAD_CURATIONS = [
        'thumbnail' => ['width' => 200, 'height' => 200, 'mode' => 'cover'],
        'medium' => ['width' => 600, 'height' => null, 'mode' => 'scale'],
        'large' => ['width' => 1200, 'height' => null, 'mode' => 'scale'],
    ];

    private const OG_CURATION = [
        'key' => 'og',
        'width' => 1200,
        'height' => 630,
        'mode' => 'cover',
    ];

    public function generateOnUpload(Media $media): void
    {
        if (! $this->isRasterImage($media)) {
            return;
        }

        foreach (self::UPLOAD_CURATIONS as $key => $params) {
            if (! $this->shouldGenerate($media, $key, $params['width'])) {
                continue;
            }

            $this->generate($media, $key, $params['width'], $params['height'], $params['mode']);
        }
    }

    public function generateOg(Media $media): void
    {
        if (! $this->isRasterImage($media)) {
            return;
        }

        if ($media->hasCuration(self::OG_CURATION['key'])) {
            return;
        }

        if (! $this->shouldGenerate($media, self::OG_CURATION['key'], self::OG_CURATION['width'])) {
            return;
        }

        $this->generate(
            $media,
            self::OG_CURATION['key'],
            self::OG_CURATION['width'],
            self::OG_CURATION['height'],
            self::OG_CURATION['mode'],
        );
    }

    public function isRasterImage(Media $media): bool
    {
        return in_array($media->type, self::RASTER_MIME_TYPES, true);
    }

    /**
     * @return array<int, string>
     */
    public function rasterMimeTypes(): array
    {
        return self::RASTER_MIME_TYPES;
    }

    public function shouldGenerate(Media $media, string $key, int $width): bool
    {
        return ($media->width ?? 0) >= $width;
    }

    public function deleteCurationFiles(Media $media): void
    {
        if (blank($media->curations)) {
            return;
        }

        $storage = Storage::disk($media->disk);

        foreach ($media->curations as $item) {
            $path = $item['curation']['path'] ?? null;

            if ($path && $storage->exists($path)) {
                $storage->delete($path);
            }
        }
    }

    public function regenerate(Media $media): void
    {
        if (! $this->isRasterImage($media)) {
            return;
        }

        $this->deleteCurationFiles($media);

        $media->curations = null;
        $media->saveQuietly();
        $media->refresh();

        $this->generateOnUpload($media);
        $this->generateOg($media);
    }

    private function generate(Media $media, string $key, int $width, ?int $height, string $mode): void
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($media->disk);
        $filePath = $storage->path($media->path);

        /** @var Api $api */
        $api = Glide::getServer()->getApi();
        $manager = $api->getImageManager();
        $image = $manager->read($filePath);

        $image->orient();

        $image = match ($mode) {
            'cover' => $image->coverDown($width, $height),
            default => $image->scaleDown($width),
        };

        $encodedImage = $image->encodeByExtension(self::CURATION_EXTENSION, quality: 85);
        $encodedContents = $encodedImage->toString();
        $fileName = $media->name.'-'.$key.'.'.self::CURATION_EXTENSION;

        $curationPath = $this->buildPath($media->directory, $fileName);

        $storage->put($curationPath, $encodedContents, $media->visibility);

        $dimensions = getimagesizefromstring($encodedContents);

        if ($dimensions === false) {
            $storage->delete($curationPath);

            Log::warning('Unable to determine generated curation dimensions.', [
                'media_id' => $media->getKey(),
                'curation_key' => $key,
            ]);

            return;
        }

        [$curationWidth, $curationHeight] = $dimensions;

        $existing = $media->curations ?? [];
        $existing[] = [
            'curation' => [
                'key' => $key,
                'disk' => $media->disk,
                'directory' => $media->directory,
                'visibility' => $media->visibility,
                'name' => $fileName,
                'path' => $curationPath,
                'width' => $curationWidth,
                'height' => $curationHeight,
                'size' => $storage->size($curationPath),
                'type' => $encodedImage->mediaType(),
                'ext' => self::CURATION_EXTENSION,
                'url' => $storage->url($curationPath),
            ],
        ];

        $media->curations = $existing;
        $media->saveQuietly();
    }

    private function buildPath(?string $directory, string $fileName): string
    {
        $normalizedDirectory = trim((string) $directory, '/');

        return blank($normalizedDirectory)
            ? $fileName
            : $normalizedDirectory.'/'.$fileName;
    }
}
