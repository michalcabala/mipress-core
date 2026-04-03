<?php

declare(strict_types=1);

namespace MiPress\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use MiPress\Core\Filament\Pages\ThemeSettings;
use MiPress\Core\Filament\Resources\BlueprintResource;
use MiPress\Core\Filament\Resources\CollectionResource;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Resources\GlobalSetResource;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Resources\TaxonomyResource;
use MiPress\Core\Filament\Resources\TermResource;
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
            ->navigationGroups([
                'Obsah',
                'Formuláře',
                'Nastavení',
                'Uživatelé',
            ])
            ->resources([
                UserResource::class,
                BlueprintResource::class,
                CollectionResource::class,
                PageResource::class,
                EntryResource::class,
                GlobalSetResource::class,
                TaxonomyResource::class,
                TermResource::class,
            ])
            ->pages([
                ThemeSettings::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()->stackedOnMobile()->deferLoading();
        });

        FilamentIcon::register([
            PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => 'fal-gauge-high',
        ]);

        FilamentView::registerRenderHook(
            PanelsRenderHook::TOPBAR_LOGO_AFTER,
            fn (): string => view('mipress::filament.components.topbar-frontend-link')->render(),
        );
    }
}
