<?php

declare(strict_types=1);

namespace MiPress\Core;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use MiPress\Core\Console\Commands\PublishScheduledEntries;
use MiPress\Core\Console\Commands\PublishThemeAssets;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\GlobalSet;
use MiPress\Core\Policies\BlueprintPolicy;
use MiPress\Core\Policies\CollectionPolicy;
use MiPress\Core\Policies\EntryPolicy;
use MiPress\Core\Policies\GlobalSetPolicy;
use MiPress\Core\Services\GlobalSetManager;
use MiPress\Core\Theme\ThemeManager;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, function (): ThemeManager {
            return new ThemeManager(resource_path('themes'));
        });

        $this->app->singleton(GlobalSetManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mipress');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->app->make(ThemeManager::class)->registerViews();

        $this->app->booted(function (): void {
            view()->composer('*', function (View $view): void {
                if (! $view->offsetExists('globals')) {
                    $manager = $this->app->make(GlobalSetManager::class);
                    $view->with('globals', $manager->all()->keyBy('handle'));
                }
            });
        });

        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Blueprint::class, BlueprintPolicy::class);
        Gate::policy(GlobalSet::class, GlobalSetPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledEntries::class,
                PublishThemeAssets::class,
            ]);
        }
    }
}
