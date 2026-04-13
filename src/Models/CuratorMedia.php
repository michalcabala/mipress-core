<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Observers\CuratorMediaObserver;

#[ObservedBy([CuratorMediaObserver::class])]
class CuratorMedia extends Media
{
    protected $fillable = [
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'alt',
        'title',
        'description',
        'caption',
        'exif',
        'curations',
        'focal_point_x',
        'focal_point_y',
        'uploaded_by',
        'file',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'curations' => 'array',
        'exif' => 'array',
        'focal_point_x' => 'integer',
        'focal_point_y' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Override parent thumbnailUrl to prefer pre-generated curation.
     * Falls back to Glide URL if curation doesn't exist.
     */
    public function thumbnailUrl(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $curationUrl = $this->getCurationUrl('nahled');

                if ($curationUrl) {
                    return $curationUrl;
                }

                return Curator::getUrlProvider()::getThumbnailUrl($this->path);
            },
        );
    }

    /**
     * Override parent mediumUrl to prefer pre-generated curation.
     */
    public function mediumUrl(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $curationUrl = $this->getCurationUrl('nahled');

                if ($curationUrl) {
                    return $curationUrl;
                }

                return Curator::getUrlProvider()::getMediumUrl($this->path);
            },
        );
    }

    public function mediaTypeLabel(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (curator()->isPreviewable($this->ext)) {
                    return curator()->isSvg($this->ext) ? 'SVG' : 'Obrázek';
                }

                if (curator()->isVideo($this->ext)) {
                    return 'Video';
                }

                if (curator()->isAudio($this->ext)) {
                    return 'Audio';
                }

                return 'Dokument';
            },
        );
    }

    private function getCurationUrl(string $key): ?string
    {
        $curation = $this->getCuration($key);

        if (filled($curation) && isset($curation['path'])) {
            $storage = Storage::disk($curation['disk'] ?? $this->disk);

            if ($storage->exists($curation['path'])) {
                return $storage->url($curation['path']);
            }
        }

        return null;
    }
}
