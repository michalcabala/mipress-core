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

];
