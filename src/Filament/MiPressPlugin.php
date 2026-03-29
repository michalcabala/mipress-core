<?php

declare(strict_types=1);

namespace MiPress\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsIconAlias;
use MiPress\Core\Filament\Pages\ThemeSettings;
use MiPress\Core\Filament\Resources\BlueprintResource;
use MiPress\Core\Filament\Resources\CollectionResource;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Resources\UserResource;

class MiPressPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'mipress';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                UserResource::class,
                BlueprintResource::class,
                CollectionResource::class,
                EntryResource::class,
            ])
            ->pages([
                ThemeSettings::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        FilamentIcon::register([
            PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => 'fal-gauge-high',
        ]);
    }
}
