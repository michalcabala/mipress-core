<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Illuminate\Console\Command;
use MiPress\Core\Theme\ThemeManager;

class PublishThemeAssets extends Command
{
    protected $signature = 'mipress:publish-assets';

    protected $description = 'Create public/themes/{slug} symlinks pointing to resources/themes/{slug}/assets';

    public function handle(ThemeManager $themeManager): int
    {
        $themes = $themeManager->discover();

        if ($themes->isEmpty()) {
            $this->warn('No themes found.');

            return self::SUCCESS;
        }

        foreach ($themes as $theme) {
            $source = resource_path("themes/{$theme->slug}/assets");
            $link = public_path("themes/{$theme->slug}");

            if (! is_dir($source)) {
                $this->warn("  Theme <comment>{$theme->slug}</comment> has no assets/ directory, skipping.");

                continue;
            }

            if (file_exists($link) || is_link($link)) {
                $this->line("  Link already exists: <comment>public/themes/{$theme->slug}</comment>");

                continue;
            }

            symlink($source, $link);

            $this->info("  Published: <comment>public/themes/{$theme->slug}</comment> → <comment>resources/themes/{$theme->slug}/assets</comment>");
        }

        $this->newLine();
        $this->info('Theme assets published successfully.');

        return self::SUCCESS;
    }
}
