<?php

declare(strict_types=1);

namespace MiPress\Core;

use Awcodes\Curator\Config\CurationManager;
use Awcodes\Curator\Curations\CurationPreset;
use Awcodes\Curator\Models\Media;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use MiPress\Core\Console\Commands\PublishScheduledEntries;
use MiPress\Core\Console\Commands\PublishThemeAssets;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\GlobalSet;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Observers\MediaObserver;
use MiPress\Core\Policies\BlueprintPolicy;
use MiPress\Core\Policies\CollectionPolicy;
use MiPress\Core\Policies\EntryPolicy;
use MiPress\Core\Policies\GlobalSetPolicy;
use MiPress\Core\Policies\MediaPolicy;
use MiPress\Core\Policies\PagePolicy;
use MiPress\Core\Policies\TaxonomyPolicy;
use MiPress\Core\Policies\TermPolicy;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\CurationGenerator;
use MiPress\Core\Services\GlobalSetManager;
use MiPress\Core\Services\MediaPathGenerator;
use MiPress\Core\Theme\ThemeManager;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeManager::class, function (): ThemeManager {
            return new ThemeManager(resource_path('themes'));
        });

        $this->app->singleton(GlobalSetManager::class);
        $this->app->singleton(BlueprintFieldResolver::class);
        $this->app->singleton(CurationGenerator::class);
        $this->app->singleton(MediaPathGenerator::class);
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

        Media::observe(MediaObserver::class);

        app(CurationManager::class)->presets([
            new CurationPreset(key: 'thumbnail', label: 'Miniatura', width: 200, height: 200, format: 'jpeg', quality: 85),
            new CurationPreset(key: 'medium', label: 'Střední', width: 600, height: null, format: 'jpeg', quality: 85),
            new CurationPreset(key: 'large', label: 'Velký', width: 1200, height: null, format: 'jpeg', quality: 85),
            new CurationPreset(key: 'og', label: 'OG Image', width: 1200, height: 630, format: 'jpeg', quality: 85),
        ]);

        Gate::policy(Media::class, MediaPolicy::class);
        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Blueprint::class, BlueprintPolicy::class);
        Gate::policy(GlobalSet::class, GlobalSetPolicy::class);
        Gate::policy(Taxonomy::class, TaxonomyPolicy::class);
        Gate::policy(Term::class, TermPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledEntries::class,
                PublishThemeAssets::class,
            ]);
        }
    }
}
