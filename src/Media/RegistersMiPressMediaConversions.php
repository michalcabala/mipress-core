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
        $dimensions = $this->resolveImageDimensions($media);

        if ($dimensions !== null) {
            $conversion->focalCropAndResize(
                $conversionConfig['w'],
                $conversionConfig['h'] ?? $conversionConfig['w'],
                (int) round($dimensions['width'] * ($media->focal_point_x / 100)),
                (int) round($dimensions['height'] * ($media->focal_point_y / 100)),
            );

            return;
        }

        $conversion->fit(
            Fit::Crop,
            $conversionConfig['w'],
            $conversionConfig['h'] ?? $conversionConfig['w'],
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

    /**
     * @return array{width: int, height: int}|null
     */
    private function resolveImageDimensions(Media $media): ?array
    {
        $width = $media->getCustomProperty('width');
        $height = $media->getCustomProperty('height');

        if (is_numeric($width) && is_numeric($height) && (int) $width > 0 && (int) $height > 0) {
            return [
                'width' => (int) $width,
                'height' => (int) $height,
            ];
        }

        $dimensions = @getimagesize($media->getPath());

        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
            return null;
        }

        $resolvedWidth = (int) $dimensions[0];
        $resolvedHeight = (int) $dimensions[1];

        if ($resolvedWidth <= 0 || $resolvedHeight <= 0) {
            return null;
        }

        $media->setCustomProperty('width', $resolvedWidth);
        $media->setCustomProperty('height', $resolvedHeight);
        $media->saveQuietly();

        return [
            'width' => $resolvedWidth,
            'height' => $resolvedHeight,
        ];
    }
}
