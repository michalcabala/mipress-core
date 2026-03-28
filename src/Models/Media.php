<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MiPress\Core\Enums\MediaType;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'model_type',
        'model_id',
        'uuid',
        'collection_name',
        'name',
        'file_name',
        'alt',
        'caption',
        'focal_point',
        'mime_type',
        'disk',
        'conversions_disk',
        'size',
        'manipulations',
        'custom_properties',
        'generated_conversions',
        'responsive_images',
        'order_column',
    ];

    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
        'focal_point' => 'array',
    ];

    public function registerMediaConversions(?BaseMedia $media = null): void
    {
        if (! $this->isImage()) {
            return;
        }

        if ($this->isSvg()) {
            return;
        }

        $focalX = $this->focal_point['x'] ?? 50;
        $focalY = $this->focal_point['y'] ?? 50;

        $this->addMediaConversion('thumbnail')
            ->fit(Fit::Crop, 150, 150)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('small')
            ->width(400)
            ->format('webp')
            ->nonQueued();

        $this->addMediaConversion('medium')
            ->width(800)
            ->format('webp');

        $this->addMediaConversion('large')
            ->width(1600)
            ->format('webp');

        $this->addMediaConversion('og')
            ->fit(Fit::Crop, 1200, 630)
            ->format('webp');
    }

    public function getMediaType(): MediaType
    {
        return MediaType::fromMimeType($this->mime_type ?? '');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }

    public function isSvg(): bool
    {
        return $this->mime_type === 'image/svg+xml';
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'video/');
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'audio/');
    }

    public function getHumanReadableSize(): string
    {
        $bytes = $this->size;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }

    public function getThumbnailUrl(): string
    {
        if ($this->isSvg()) {
            return $this->getUrl();
        }

        if ($this->isImage() && $this->hasGeneratedConversion('thumbnail')) {
            return $this->getUrl('thumbnail');
        }

        return '';
    }

    public function getUploader(): ?BelongsTo
    {
        return null;
    }
}
