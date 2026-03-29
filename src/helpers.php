<?php

declare(strict_types=1);

use MiPress\Core\Theme\ThemeManager;

if (! function_exists('theme_asset')) {
    function theme_asset(string $path): string
    {
        $slug = app(ThemeManager::class)->getActive();

        return asset("themes/{$slug}/{$path}");
    }
}
