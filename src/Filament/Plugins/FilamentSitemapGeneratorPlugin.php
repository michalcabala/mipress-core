<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Plugins;

use Filament\Panel;
use MiPress\Core\Filament\Pages\SitemapGeneratorPage;
use MiPress\Core\Filament\Pages\SitemapSettingsPage;

class FilamentSitemapGeneratorPlugin extends \MuhammadNawlo\FilementSitemapGenerator\FilamentSitemapGeneratorPlugin
{
    public function register(Panel $panel): void
    {
        $panel->pages([
            SitemapGeneratorPage::class,
            SitemapSettingsPage::class,
        ]);
    }
}
