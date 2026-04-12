<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Curations\CurationPreset;
use Awcodes\Curator\Facades\Curation;
use Awcodes\Curator\Facades\Glide;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Models\CuratorMedia;

class FocalPointCropper
{
    /**
     * Generate all curation presets for a media record using its focal point.
     *
     * @return array<int, array{curation: array<string, mixed>}>
     */
    public function generateAll(CuratorMedia $media): array
    {
        if (! is_media_resizable($media->ext)) {
            return [];
        }

        $curations = [];

        foreach (Curation::getPresets() as $preset) {
            $curation = $this->generatePreset($media, $preset);

            if ($curation !== null) {
                $curations[] = ['curation' => $curation];
            }
        }

        return $curations;
    }

    /**
     * Generate a single curation preset for a media record.
     *
     * @return array<string, mixed>|null
     */
    public function generatePreset(CuratorMedia $media, CurationPreset $preset): ?array
    {
        $storage = Storage::disk($media->disk);
        $filePath = $storage->path($media->path);

        if (! file_exists($filePath)) {
            return null;
        }

        $manager = Glide::getServer()->getApi()->getImageManager();
        $image = $manager->read($filePath);
        $image->orient();

        $srcW = $image->width();
        $srcH = $image->height();

        $targetW = $preset->getWidth();
        $targetH = $preset->getHeight();
        $extension = $preset->getFormat();
        $quality = $preset->getQuality();

        $focalX = $media->focal_point_x ?? 50;
        $focalY = $media->focal_point_y ?? 50;

        [$cropX, $cropY, $cropW, $cropH] = $this->calculateCropRegion(
            $srcW, $srcH, $targetW, $targetH, $focalX, $focalY,
        );

        $encodedImage = $image
            ->crop($cropW, $cropH, $cropX, $cropY)
            ->resize($targetW, $targetH)
            ->encodeByExtension(extension: $extension, quality: $quality);

        $curationPath = $media->directory.'/'.$media->name.'/'.$preset->getKey().'.'.$extension;
        $storage->put($curationPath, $encodedImage);

        return [
            'key' => $preset->getKey(),
            'disk' => $media->disk,
            'directory' => $media->name,
            'visibility' => $media->visibility,
            'name' => $preset->getKey().'.'.$extension,
            'path' => $curationPath,
            'width' => $targetW,
            'height' => $targetH,
            'size' => $storage->size($curationPath),
            'type' => $encodedImage->mediaType(),
            'ext' => $extension,
            'url' => $storage->url($curationPath),
        ];
    }

    /**
     * Calculate the optimal crop region centered on the focal point.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} [x, y, width, height]
     */
    public function calculateCropRegion(
        int $srcW,
        int $srcH,
        int $targetW,
        int $targetH,
        int $focalX,
        int $focalY,
    ): array {
        $targetRatio = $targetW / $targetH;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
        }

        $focalPxX = (int) round($srcW * ($focalX / 100));
        $focalPxY = (int) round($srcH * ($focalY / 100));

        $cropX = (int) max(0, min($srcW - $cropW, $focalPxX - $cropW / 2));
        $cropY = (int) max(0, min($srcH - $cropH, $focalPxY - $cropH / 2));

        return [$cropX, $cropY, $cropW, $cropH];
    }
}
