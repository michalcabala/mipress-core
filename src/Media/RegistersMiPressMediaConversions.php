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
        $modelMedia = $media instanceof Media ? $media : null;

        foreach (MediaConfig::conversionDefinitions() as $conversionConfig) {
            if (! $this->shouldRegisterAutomaticConversion($conversionConfig, $modelMedia)) {
                continue;
            }

            $conversion = $this->addMediaConversion($conversionConfig['name'])
                ->format('webp')
                ->quality(MediaConfig::conversionQuality())
                ->queued();

            if (MediaConfig::usesCropMode($conversionConfig['mode'] ?? null)) {
                $this->applyCropManipulations($conversion, $conversionConfig, $modelMedia);

                continue;
            }

            $this->applyResizeManipulations($conversion, $conversionConfig);
        }
    }

    /**
     * @param  array<string, mixed>  $conversionConfig
     */
    private function applyCropManipulations(Conversion $conversion, array $conversionConfig, ?Media $media): void
    {
        $strategy = MediaConfig::defaultCropStrategy($conversionConfig);

        if ($strategy !== 'focal_point' || ! ($conversionConfig['supports_focal_point'] ?? false) || ! $media instanceof Media) {
            $conversion->fit(
                Fit::Crop,
                (int) $conversionConfig['w'],
                (int) ($conversionConfig['h'] ?? $conversionConfig['w']),
            );

            return;
        }

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

    /**
     * @param  array<string, mixed>  $conversionConfig
     */
    private function shouldRegisterAutomaticConversion(array $conversionConfig, ?Media $media): bool
    {
        if (! MediaConfig::usesCropMode($conversionConfig['mode'] ?? null)) {
            return true;
        }

        $strategy = MediaConfig::defaultCropStrategy($conversionConfig);

        if (in_array($strategy, ['manual', 'none'], true)) {
            return false;
        }

        if (
            $media instanceof Media
            && (bool) ($conversionConfig['supports_manual_crop'] ?? false)
            && $media->hasManualConversionOverride((string) ($conversionConfig['name'] ?? ''))
        ) {
            return false;
        }

        return true;
    }
}
