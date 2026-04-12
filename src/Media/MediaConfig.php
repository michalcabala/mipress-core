<?php

declare(strict_types=1);

namespace MiPress\Core\Media;

final class MediaConfig
{
    public const DISK = 'local_uploads';

    public const MAX_UPLOAD_SIZE = 20 * 1024 * 1024;

    public const CONVERSION_QUALITY = 85;

    public const COLLECTION_LIBRARY = 'library';

    public const COLLECTION_FEATURED = 'featured_image';

    public const COLLECTION_GALLERY = 'gallery';

    /**
     * @var array<int, array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}>
     */
    public const CONVERSIONS = [
        [
            'name' => 'thumbnail',
            'label' => 'Miniatura',
            'w' => 200,
            'h' => 200,
            'mode' => 'crop',
        ],
        [
            'name' => 'medium',
            'label' => 'Střední',
            'w' => 600,
            'h' => null,
            'mode' => 'resize',
        ],
        [
            'name' => 'large',
            'label' => 'Velký',
            'w' => 1200,
            'h' => null,
            'mode' => 'resize',
        ],
        [
            'name' => 'og',
            'label' => 'Open Graph',
            'w' => 1200,
            'h' => 630,
            'mode' => 'crop',
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const ALLOWED_MIME_TYPES = [
        'images' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/svg+xml',
        ],
        'documents' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/zip',
        ],
        'video' => [
            'video/mp4',
            'video/webm',
            'video/quicktime',
        ],
    ];

    private function __construct() {}

    public static function disk(): string
    {
        return self::DISK;
    }

    public static function maxUploadSize(): int
    {
        return self::MAX_UPLOAD_SIZE;
    }

    public static function conversionQuality(): int
    {
        return self::CONVERSION_QUALITY;
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}>
     */
    public static function conversions(): array
    {
        return self::CONVERSIONS;
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}>
     */
    public static function cropConversions(): array
    {
        return array_values(array_filter(
            self::CONVERSIONS,
            static fn (array $conversion): bool => $conversion['mode'] === 'crop',
        ));
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}>
     */
    public static function conversionsForJs(): array
    {
        return self::CONVERSIONS;
    }

    /**
     * @return array<int, string>
     */
    public static function conversionNames(): array
    {
        return array_map(
            static fn (array $conversion): string => $conversion['name'],
            self::CONVERSIONS,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function allowedMimeTypesByGroup(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedMimeTypes(): array
    {
        return array_values(array_merge(...array_values(self::ALLOWED_MIME_TYPES)));
    }

    /**
     * @return array<int, string>
     */
    public static function allowedMimeTypesForGroup(string $group): array
    {
        return self::ALLOWED_MIME_TYPES[$group] ?? [];
    }

    public static function featuredCollection(): string
    {
        return self::COLLECTION_FEATURED;
    }

    public static function galleryCollection(): string
    {
        return self::COLLECTION_GALLERY;
    }

    public static function libraryCollection(): string
    {
        return self::COLLECTION_LIBRARY;
    }
}
