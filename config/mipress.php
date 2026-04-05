<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk used for storing uploaded media files.
    |
    */

    'media_disk' => env('MIPRESS_MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Mason Bricks
    |--------------------------------------------------------------------------
    |
    | Additional Mason brick classes to register alongside the built-in ones.
    | Each entry should be a fully qualified class name.
    |
    */

    'extra_bricks' => [],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel Path
    |--------------------------------------------------------------------------
    |
    | The URL path prefix for the Filament admin panel.
    |
    */

    'admin_path' => env('MIPRESS_ADMIN_PATH', 'mpcp'),

    /*
    |--------------------------------------------------------------------------
    | Sitemap
    |--------------------------------------------------------------------------
    |
    | Configure which Eloquent models are included in the generated sitemap.
    | Each entry maps a model class to its default priority and change
    | frequency. Models must have a getPublicUrl() method and a
    | scopePublished() query scope.
    |
    */

    'sitemap' => [
        'models' => [
            \MiPress\Core\Models\Entry::class => [
                'priority' => 0.8,
                'changefreq' => 'weekly',
            ],
            \MiPress\Core\Models\Page::class => [
                'priority' => 0.6,
                'changefreq' => 'monthly',
            ],
        ],
    ],

];
