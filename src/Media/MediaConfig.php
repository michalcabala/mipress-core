<?php

declare(strict_types=1);

namespace MiPress\Core\Media;

use MiPress\Core\Services\SettingsManager;

final class MediaConfig
{
    public const DISK = 'local_uploads';

    public const SETTINGS_HANDLE = 'media_conversions';

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
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}>
     */
    public static function builtInConversions(): array
    {
        $conversions = [];

        foreach (self::CONVERSIONS as $index => $conversion) {
            $conversions[] = [
                'name' => (string) ($conversion['name'] ?? 'conversion_'.$index),
                'label' => (string) ($conversion['label'] ?? 'Konverze '.($index + 1)),
                'w' => (int) ($conversion['w'] ?? 0),
                'h' => is_numeric($conversion['h'] ?? null) ? (int) $conversion['h'] : null,
                'mode' => ($conversion['name'] ?? null) === 'thumbnail'
                    ? 'crop_resize'
                    : (self::usesCropMode($conversion['mode'] ?? null) ? 'crop' : 'resize'),
                'is_active' => true,
                'show_in_editor' => true,
                'sort_order' => $index + 1,
                'group' => match ((string) ($conversion['name'] ?? '')) {
                    'thumbnail' => 'thumbnails',
                    'og' => 'social',
                    default => 'content',
                },
                'editor_badge' => match ((string) ($conversion['name'] ?? '')) {
                    'thumbnail' => 'thumbnail',
                    'og' => 'social',
                    default => 'content',
                },
                'description' => match ((string) ($conversion['name'] ?? '')) {
                    'thumbnail' => 'Kompaktní výstup pro výpisy a karty obsahu.',
                    'og' => 'Sdílecí výstup pro Open Graph a sociální sítě.',
                    default => 'Běžná výstupní varianta obrázku.',
                },
                'supports_focal_point' => self::usesCropMode(($conversion['name'] ?? null) === 'thumbnail' ? 'crop_resize' : ($conversion['mode'] ?? null)),
                'supports_manual_crop' => self::usesCropMode(($conversion['name'] ?? null) === 'thumbnail' ? 'crop_resize' : ($conversion['mode'] ?? null)),
                'manual_crop_required' => false,
                'default_crop_strategy' => self::usesCropMode(($conversion['name'] ?? null) === 'thumbnail' ? 'crop_resize' : ($conversion['mode'] ?? null))
                    ? 'focal_point'
                    : 'none',
                'important' => in_array((string) ($conversion['name'] ?? ''), ['thumbnail', 'og'], true),
                'priority' => in_array((string) ($conversion['name'] ?? ''), ['thumbnail', 'og'], true) ? 'high' : 'normal',
                'editor_help_text' => match ((string) ($conversion['name'] ?? '')) {
                    'thumbnail' => 'Použijte tam, kde je důležitá kontrola výřezu a čitelnost kompozice.',
                    'og' => 'Hlídejte bezpečný výřez pro sdílení a titulkové overlaye.',
                    default => 'Standardní systémová varianta bez speciálního zásahu.',
                },
                'usage_context' => match ((string) ($conversion['name'] ?? '')) {
                    'thumbnail' => 'Výpis článků, karty obsahu, menší přehledové bloky.',
                    'medium' => 'Běžný obsah stránky a průběžné ilustrační obrázky.',
                    'large' => 'Větší layouty a výraznější obsahové bloky.',
                    'og' => 'OG image při sdílení stránek a obsahu.',
                    default => 'Použití v systému doplňte podle potřeby.',
                },
            ];
        }

        return $conversions;
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'crop'|'resize'}>
     */
    public static function conversions(): array
    {
        return array_map(static function (array $conversion): array {
            return [
                'name' => $conversion['name'],
                'label' => $conversion['label'],
                'w' => $conversion['w'],
                'h' => $conversion['h'],
                'mode' => self::usesCropMode($conversion['mode'] ?? null) ? 'crop' : 'resize',
            ];
        }, self::configuredConversions());
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
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int}>
     */
    public static function editorConversions(): array
    {
        return array_values(array_filter(
            self::configuredConversions(),
            static fn (array $conversion): bool => (bool) ($conversion['show_in_editor'] ?? true),
        ));
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int}>
     */
    public static function editorCropConversions(): array
    {
        return array_values(array_filter(
            self::editorConversions(),
            static fn (array $conversion): bool => self::usesCropMode($conversion['mode'] ?? null),
        ));
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int}>
     */
    public static function editorManualCropConversions(): array
    {
        return array_values(array_filter(
            self::editorCropConversions(),
            static fn (array $conversion): bool => (bool) ($conversion['supports_manual_crop'] ?? false),
        ));
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}>
     */
    public static function conversionDefinitions(): array
    {
        return self::configuredConversions();
    }

    /**
     * @return array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}|null
     */
    public static function findConversion(string $conversionName): ?array
    {
        foreach (self::configuredConversions() as $conversion) {
            if (($conversion['name'] ?? null) === $conversionName) {
                return $conversion;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $conversion
     */
    public static function defaultCropStrategy(array $conversion): string
    {
        $fallbackStrategy = self::usesCropMode($conversion['mode'] ?? null)
            ? 'focal_point'
            : 'none';

        $strategy = (string) ($conversion['default_crop_strategy'] ?? $fallbackStrategy);

        return in_array($strategy, ['none', 'center', 'focal_point', 'manual'], true)
            ? $strategy
            : $fallbackStrategy;
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int}>
     */
    public static function conversionsForJs(): array
    {
        return self::editorConversions();
    }

    /**
     * @return array<int, string>
     */
    public static function conversionNames(): array
    {
        return array_map(
            static fn (array $conversion): string => $conversion['name'],
            self::conversions(),
        );
    }

    public static function usesCropMode(?string $mode): bool
    {
        return in_array((string) $mode, ['crop', 'crop_resize'], true);
    }

    public static function usesResizeOutputMode(?string $mode): bool
    {
        return in_array((string) $mode, ['resize', 'crop_resize'], true);
    }

    public static function resolveVariantName(string $variant): ?string
    {
        return match ($variant) {
            'default' => null,
            'avatar', 'thumbnail' => self::findConversionName('thumbnail', ['thumbnail'], ['thumbnails']),
            'card', 'medium' => self::findConversionName('medium', ['content'], ['content']),
            'hero', 'large' => self::findConversionName('large', ['hero'], ['hero']),
            'og' => self::findConversionName('og', ['social'], ['social']),
            default => null,
        };
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

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}>
     */
    private static function configuredConversions(): array
    {
        $configured = self::configuredConversionsFromSettings();

        return $configured !== [] ? $configured : self::builtInConversions();
    }

    /**
     * @return array<int, array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}>
     */
    private static function configuredConversionsFromSettings(): array
    {
        if (! app()->bound(SettingsManager::class)) {
            return [];
        }

        $storedConversions = app(SettingsManager::class)->get(self::SETTINGS_HANDLE, 'conversions', []);

        if (! is_array($storedConversions)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($storedConversions) as $index => $conversion) {
            if (! is_array($conversion)) {
                continue;
            }

            $normalizedConversion = self::normalizeConfiguredConversion($conversion, $index);

            if ($normalizedConversion === null || ! $normalizedConversion['is_active']) {
                continue;
            }

            $normalized[] = $normalizedConversion;
        }

        usort($normalized, static function (array $left, array $right): int {
            $sortComparison = $left['sort_order'] <=> $right['sort_order'];

            if ($sortComparison !== 0) {
                return $sortComparison;
            }

            return $left['name'] <=> $right['name'];
        });

        return array_values($normalized);
    }

    /**
     * @param  array<string, mixed>  $conversion
     * @return array{name: string, label: string, w: int, h: int|null, mode: 'resize'|'crop'|'crop_resize', is_active: bool, show_in_editor: bool, sort_order: int, group?: string, editor_badge?: string, description?: string, supports_focal_point?: bool, supports_manual_crop?: bool, manual_crop_required?: bool, default_crop_strategy?: string, important?: bool, priority?: string, editor_help_text?: string, usage_context?: string}|null
     */
    private static function normalizeConfiguredConversion(array $conversion, int $index): ?array
    {
        $name = trim((string) ($conversion['name'] ?? ''));

        if ($name === '' || ! preg_match('/^[a-z0-9_]+$/', $name)) {
            return null;
        }

        $width = $conversion['width'] ?? $conversion['w'] ?? null;

        if (! is_numeric($width) || (int) $width <= 0) {
            return null;
        }

        $height = $conversion['height'] ?? $conversion['h'] ?? null;
        $normalizedHeight = (is_numeric($height) && ((int) $height > 0)) ? (int) $height : null;

        $mode = (string) ($conversion['mode'] ?? 'resize');

        if (! in_array($mode, ['resize', 'crop', 'crop_resize'], true)) {
            $mode = 'resize';
        }

        return [
            'name' => $name,
            'label' => trim((string) ($conversion['label'] ?? '')) ?: $name,
            'w' => (int) $width,
            'h' => $normalizedHeight,
            'mode' => $mode,
            'is_active' => (bool) ($conversion['is_active'] ?? true),
            'show_in_editor' => (bool) ($conversion['show_in_editor'] ?? true),
            'sort_order' => (int) ($conversion['sort_order'] ?? ($index + 1)),
            'group' => filled($conversion['group'] ?? null) ? (string) $conversion['group'] : null,
            'editor_badge' => filled($conversion['editor_badge'] ?? null) ? (string) $conversion['editor_badge'] : null,
            'description' => filled($conversion['description'] ?? null) ? (string) $conversion['description'] : null,
            'supports_focal_point' => self::usesCropMode($mode) ? (bool) ($conversion['supports_focal_point'] ?? true) : false,
            'supports_manual_crop' => self::usesCropMode($mode) ? (bool) ($conversion['supports_manual_crop'] ?? false) : false,
            'manual_crop_required' => self::usesCropMode($mode) ? (bool) ($conversion['manual_crop_required'] ?? false) : false,
            'default_crop_strategy' => self::usesCropMode($mode)
                ? (string) ($conversion['default_crop_strategy'] ?? 'focal_point')
                : 'none',
            'important' => (bool) ($conversion['important'] ?? false),
            'priority' => filled($conversion['priority'] ?? null) ? (string) $conversion['priority'] : 'normal',
            'editor_help_text' => filled($conversion['editor_help_text'] ?? null) ? (string) $conversion['editor_help_text'] : null,
            'usage_context' => filled($conversion['usage_context'] ?? null) ? (string) $conversion['usage_context'] : null,
        ];
    }

    /**
     * @param  array<int, string>  $preferredBadges
     * @param  array<int, string>  $preferredGroups
     */
    private static function findConversionName(string $preferredName, array $preferredBadges = [], array $preferredGroups = []): ?string
    {
        foreach (self::configuredConversions() as $conversion) {
            if (($conversion['name'] ?? null) === $preferredName) {
                return $conversion['name'];
            }
        }

        foreach (self::configuredConversions() as $conversion) {
            if (in_array((string) ($conversion['editor_badge'] ?? ''), $preferredBadges, true)) {
                return $conversion['name'];
            }
        }

        foreach (self::configuredConversions() as $conversion) {
            if (in_array((string) ($conversion['group'] ?? ''), $preferredGroups, true)) {
                return $conversion['name'];
            }
        }

        return null;
    }
}
