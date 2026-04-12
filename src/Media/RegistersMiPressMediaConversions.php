<?php

declare(strict_types=1);

namespace MiPress\Core\Media;

use MiPress\Core\Models\Media;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

trait RegistersMiPressMediaConversions
{
    public bool $registerMediaConversionsUsingModelInstance = true;

    public function registerMediaConversions(?SpatieMedia $media = null): void
    {
        foreach (MediaConfig::conversions() as $conversionConfig) {
            $conversion = $this->addMediaConversion($conversionConfig['name'])
                ->format('webp')
                ->quality(MediaConfig::conversionQuality())
                ->queued();

            if ($conversionConfig['mode'] === 'crop' && $media instanceof Media) {
                $this->applyCropManipulations($conversion, $conversionConfig, $media);

                continue;
            }

            $this->applyResizeManipulations($conversion, $conversionConfig);
        }
    }

    /**
     * @param  array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}  $conversionConfig
     */
    private function applyCropManipulations(Conversion $conversion, array $conversionConfig, Media $media): void
    {
        $focalX = is_numeric($media->focal_point_x) ? (int) $media->focal_point_x : 50;
        $focalY = is_numeric($media->focal_point_y) ? (int) $media->focal_point_y : 50;

        $conversion->focalCropAndResize(
            $conversionConfig['w'],
            $conversionConfig['h'] ?? $conversionConfig['w'],
            $focalX,
            $focalY,
        );
    }

    /**
     * @param  array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}  $conversionConfig
     */
    private function applyResizeManipulations(Conversion $conversion, array $conversionConfig): void
    {
        $height = $conversionConfig['h'];

        if (is_int($height) && $height > 0) {
            $conversion->fit(Fit::Max, $conversionConfig['w'], $height);

            return;
        }

        $conversion->width($conversionConfig['w']);
    }
}
