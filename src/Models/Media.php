<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use MiPress\Core\Media\MediaConfig;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    protected $fillable = [
        'name',
        'file_name',
        'mime_type',
        'disk',
        'conversions_disk',
        'size',
        'manipulations',
        'custom_properties',
        'generated_conversions',
        'responsive_images',
        'order_column',
        'focal_point_x',
        'focal_point_y',
        'uploaded_by',
    ];

    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
        'focal_point_x' => 'integer',
        'focal_point_y' => 'integer',
        'uploaded_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->focal_point_x ??= 50;
            $media->focal_point_y ??= 50;

            if ($media->uploaded_by === null && auth()->check()) {
                $media->uploaded_by = (int) auth()->id();
            }
        });

        static::created(function (self $media): void {
            $media->storeImageDimensions();
        });
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeLibraryItems(Builder $query): Builder
    {
        return $query->where('model_type', Attachment::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function isDocument(): bool
    {
        return ! $this->isImage() && ! $this->isVideo();
    }

    public function isLibraryItem(): bool
    {
        return $this->model_type === Attachment::class;
    }

    public function mimeGroup(): string
    {
        if ($this->isImage()) {
            return 'image';
        }

        if ($this->isVideo()) {
            return 'video';
        }

        return in_array((string) $this->mime_type, MediaConfig::allowedMimeTypesForGroup('documents'), true)
            ? 'document'
            : 'other';
    }

    public function alt(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->getCustomProperty('alt'),
            set: fn (?string $value): array => [
                'custom_properties' => $this->encodeCustomPropertiesForMutation('alt', $value),
            ],
        );
    }

    public function title(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->getCustomProperty('title'),
            set: fn (?string $value): array => [
                'custom_properties' => $this->encodeCustomPropertiesForMutation('title', $value),
            ],
        );
    }

    public function humanReadableSize(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $size = (int) $this->size;

                if ($size >= 1024 * 1024) {
                    return number_format($size / (1024 * 1024), 1, ',', ' ').' MB';
                }

                if ($size >= 1024) {
                    return number_format($size / 1024, 1, ',', ' ').' KB';
                }

                return $size.' B';
            },
        );
    }

    private function storeImageDimensions(): void
    {
        if (! $this->isImage() || ! File::exists($this->getPath())) {
            return;
        }

        $dimensions = @getimagesize($this->getPath());

        if (! is_array($dimensions) || ! isset($dimensions[0], $dimensions[1])) {
            return;
        }

        $this->setCustomProperty('width', (int) $dimensions[0]);
        $this->setCustomProperty('height', (int) $dimensions[1]);
        $this->saveQuietly();
    }

    /**
     * @return array<string, mixed>
     */
    private function customPropertiesForMutation(string $key, ?string $value): array
    {
        $properties = $this->resolveRawCustomProperties();

        if (blank($value)) {
            unset($properties[$key]);

            return $properties;
        }

        $properties[$key] = $value;

        return $properties;
    }

    private function encodeCustomPropertiesForMutation(string $key, ?string $value): string
    {
        return (string) json_encode(
            $this->customPropertiesForMutation($key, $value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRawCustomProperties(): array
    {
        $rawProperties = $this->attributes['custom_properties'] ?? [];

        if (is_array($rawProperties)) {
            return $rawProperties;
        }

        if (! is_string($rawProperties) || trim($rawProperties) === '') {
            return [];
        }

        $decodedProperties = json_decode($rawProperties, true);

        return is_array($decodedProperties) ? $decodedProperties : [];
    }
}
