<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Storage;

class CurationGenerator
{
    private const RASTER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const UPLOAD_CURATIONS = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'mode' => 'cover'],
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
        $storage = Storage::disk($media->disk);
        $filePath = $storage->path($media->path);

        $manager = Glide::getServer()->getApi()->getImageManager();
        $image = $manager->read($filePath);

        $image->orient();

        $image = match ($mode) {
            'cover' => $image->coverDown($width, $height),
            default => $image->scaleDown($width),
        };

        $encodedImage = $image->encodeByExtension($media->ext, quality: 85);

        $curationPath = $media->directory.'/'.$media->name.'-'.$key.'.'.$media->ext;

        $storage->put($curationPath, $encodedImage->toString(), $media->visibility);

        [$curationWidth, $curationHeight] = getimagesizefromstring($encodedImage->toString());

        $existing = $media->curations ?? [];
        $existing[] = [
            'curation' => [
                'key' => $key,
                'disk' => $media->disk,
                'directory' => $media->directory,
                'visibility' => $media->visibility,
                'name' => $media->name.'-'.$key.'.'.$media->ext,
                'path' => $curationPath,
                'width' => $curationWidth,
                'height' => $curationHeight,
                'size' => $storage->size($curationPath),
                'type' => $encodedImage->mediaType(),
                'ext' => $media->ext,
                'url' => $storage->url($curationPath),
            ],
        ];

        $media->curations = $existing;
        $media->saveQuietly();
    }
}
