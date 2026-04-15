<?php

declare(strict_types=1);

namespace MiPress\Core\Filament;

use Awcodes\Curator\CuratorPlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables\Table;
use Filament\View\PanelsIconAlias;
use MiPress\Core\Filament\Pages\EditSettings;
use MiPress\Core\Filament\Pages\GlobalSeoSettings;
use MiPress\Core\Filament\Pages\SitemapSettings;
use MiPress\Core\Filament\Pages\ThemeSettings;
use MiPress\Core\Filament\Plugins\BotlyPlugin;
use MiPress\Core\Filament\Resources\BlueprintResource;
use MiPress\Core\Filament\Resources\CollectionResource;
use MiPress\Core\Filament\Resources\EntryResource;
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
            ->discoverClusters(in: __DIR__.'/Clusters', for: 'MiPress\\Core\\Filament\\Clusters')
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('mipress::admin.plugin.navigation_groups.content')),
                NavigationGroup::make(fn (): string => __('mipress::admin.plugin.navigation_groups.forms')),
                NavigationGroup::make(fn (): string => __('mipress::admin.plugin.navigation_groups.social_feeds')),
                NavigationGroup::make(fn (): string => __('mipress::admin.plugin.navigation_groups.settings')),
                NavigationGroup::make(fn (): string => __('mipress::admin.plugin.navigation_groups.users')),
            ])
            ->resources([
                UserResource::class,
                BlueprintResource::class,
                CollectionResource::class,
                PageResource::class,
                EntryResource::class,
                TaxonomyResource::class,
                TermResource::class,
            ])
            ->pages([
                EditSettings::class,
                GlobalSeoSettings::class,
                ThemeSettings::class,
                SitemapSettings::class,
            ])
            ->plugin(
                BotlyPlugin::make()
                    ->navigationIcon('fal-user-robot')
                    ->title(fn (): string => __('mipress::admin.plugin.botly_title'))
            )
            ->plugin(
                CuratorPlugin::make()
                    ->label(fn (): string => __('mipress::admin.plugin.curator.label'))
                    ->pluralLabel(fn (): string => __('mipress::admin.plugin.curator.plural_label'))
                    ->navigationGroup(fn (): string => __('mipress::admin.plugin.curator.navigation_group'))
                    ->navigationIcon('fal-photo-film-music')
                    ->navigationSort(90)
                    ->curations()
                    ->fileSwap()
            );
    }

    public function boot(Panel $panel): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->striped()->stackedOnMobile()->deferLoading();
        });

        FilamentIcon::register([
            PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => 'fal-gauge-high',
        ]);
    }
}
