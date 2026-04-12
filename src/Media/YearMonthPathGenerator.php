<?php

declare(strict_types=1);

namespace MiPress\Core\Media;

use Carbon\CarbonImmutable;
use MiPress\Core\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class YearMonthPathGenerator implements PathGenerator
{
    public function getPath(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string
    {
        return $this->basePath($media);
    }

    public function getPathForConversions(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string
    {
        return $this->basePath($media).'conversions/';
    }

    public function getPathForResponsiveImages(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string
    {
        return $this->basePath($media).'responsive/';
    }

    private function basePath(\Spatie\MediaLibrary\MediaCollections\Models\Media $media): string
    {
        $date = CarbonImmutable::now();

        if ($media instanceof Media && $media->created_at !== null) {
            $date = CarbonImmutable::instance($media->created_at);
        }

        return sprintf('%s/%s/%s/', $date->format('Y'), $date->format('m'), $media->getKey());
    }
}
