<?php

declare(strict_types=1);

namespace MiPress\Core;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MiPress\Core\Console\Commands\PublishScheduledEntries;
use MiPress\Core\Console\Commands\PublishThemeAssets;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Policies\BlueprintPolicy;
use MiPress\Core\Policies\CollectionPolicy;
use MiPress\Core\Policies\EntryPolicy;
use MiPress\Core\Theme\ThemeManager;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, function (): ThemeManager {
            return new ThemeManager(resource_path('themes'));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mipress');

        $this->app->make(ThemeManager::class)->registerViews();

        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Blueprint::class, BlueprintPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledEntries::class,
                PublishThemeAssets::class,
            ]);
        }
    }
}
