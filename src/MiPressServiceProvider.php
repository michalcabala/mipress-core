<?php

declare(strict_types=1);

namespace MiPress\Core;

use Awcodes\Curator\Curations\CurationPreset;
use Awcodes\Curator\Facades\Curation;
use Awcodes\Curator\Facades\Glide;
use Awcodes\Curator\Glide\SymfonyResponseFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use MiPress\Core\Console\Commands\GenerateSitemap;
use MiPress\Core\Console\Commands\PublishScheduledEntries;
use MiPress\Core\Console\Commands\PublishScheduledPages;
use MiPress\Core\Console\Commands\PublishThemeAssets;
use MiPress\Core\FieldTypes\FieldTypeRegistry;
use MiPress\Core\FieldTypes\Types;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\CuratorMedia;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\GlobalSet;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Observers\ContentObserver;
use MiPress\Core\Policies\BlueprintPolicy;
use MiPress\Core\Policies\CollectionPolicy;
use MiPress\Core\Policies\CuratorMediaPolicy;
use MiPress\Core\Policies\EntryPolicy;
use MiPress\Core\Policies\GlobalSetPolicy;
use MiPress\Core\Policies\PagePolicy;
use MiPress\Core\Policies\TaxonomyPolicy;
use MiPress\Core\Policies\TermPolicy;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\GlobalSeoSettingsManager;
use MiPress\Core\Services\MediaUrlGenerator;
use MiPress\Core\Services\SeoResolver;
use MiPress\Core\Services\SettingsManager;
use MiPress\Core\Services\SitemapGenerator;
use MiPress\Core\Theme\ThemeManager;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mipress.php', 'mipress');

        $this->app->singleton(ThemeManager::class, function (): ThemeManager {
            return new ThemeManager(resource_path('themes'));
        });

        $this->app->singleton(GlobalSeoSettingsManager::class);
        $this->app->singleton(SeoResolver::class);
        $this->app->singleton(SettingsManager::class);
        $this->app->singleton(BlueprintFieldResolver::class);
        $this->app->singleton(MediaUrlGenerator::class);
        $this->app->singleton(SitemapGenerator::class);

        $this->app->singleton(FieldTypeRegistry::class, function (): FieldTypeRegistry {
            $registry = new FieldTypeRegistry;

            $builtInTypes = [
                Types\TextFieldType::class,
                Types\TextareaFieldType::class,
                Types\RichTextFieldType::class,
                Types\MarkdownFieldType::class,
                Types\NumberFieldType::class,
                Types\SelectFieldType::class,
                Types\CheckboxFieldType::class,
                Types\ToggleFieldType::class,
                Types\RadioFieldType::class,
                Types\DateFieldType::class,
                Types\DateTimeFieldType::class,
                Types\ImageFieldType::class,
                Types\FileFieldType::class,
                Types\ColorFieldType::class,
                Types\TagsFieldType::class,
                Types\RepeaterFieldType::class,
                Types\KeyValueFieldType::class,
                Types\HiddenFieldType::class,
            ];

            foreach ($builtInTypes as $type) {
                $registry->register($type);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'mipress');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mipress');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'mipress');

        $this->publishes([
            __DIR__.'/../lang/vendor/curator' => $this->app->langPath('vendor/curator'),
        ], 'mipress-curator-lang');

        Glide::configure()->serverConfig([
            'response' => new SymfonyResponseFactory(app('request')),
            'source' => storage_path('app'),
            'source_path_prefix' => 'public/uploads',
            'cache' => storage_path('app'),
            'cache_path_prefix' => '.cache',
            'max_image_size' => 2000 * 2000,
            'base_url' => 'curator',
        ]);

        Curation::presets([
            CurationPreset::make(__('mipress::admin.curations.preview'))
                ->width(400)
                ->height(400)
                ->format('webp')
                ->quality(85),
            CurationPreset::make(__('mipress::admin.curations.open_graph'))
                ->width(1200)
                ->height(630)
                ->format('webp')
                ->quality(85),
            CurationPreset::make(__('mipress::admin.curations.square'))
                ->width(1200)
                ->height(1200)
                ->format('webp')
                ->quality(85),
            CurationPreset::make(__('mipress::admin.curations.widescreen'))
                ->width(1600)
                ->height(900)
                ->format('webp')
                ->quality(85),
            CurationPreset::make(__('mipress::admin.curations.standard'))
                ->width(1600)
                ->height(1200)
                ->format('webp')
                ->quality(85),
        ]);

        $this->app->make(ThemeManager::class)->registerViews();

        $this->app->booted(function (): void {
            view()->composer('mipress::*', function (View $view): void {
                if (! $view->offsetExists('settings')) {
                    $view->with('settings', $this->app->make(SettingsManager::class));
                }
            });
        });

        Entry::observe(ContentObserver::class);
        Page::observe(ContentObserver::class);

        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Blueprint::class, BlueprintPolicy::class);
        Gate::policy(GlobalSet::class, GlobalSetPolicy::class);
        Gate::policy(Taxonomy::class, TaxonomyPolicy::class);
        Gate::policy(Term::class, TermPolicy::class);
        Gate::policy(CuratorMedia::class, CuratorMediaPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mipress.php' => config_path('mipress.php'),
            ], 'mipress-config');

            $this->publishes([
                __DIR__.'/../config/curator.php' => config_path('curator.php'),
            ], 'mipress-curator-config');

            $this->commands([
                GenerateSitemap::class,
                PublishScheduledEntries::class,
                PublishScheduledPages::class,
                PublishThemeAssets::class,
            ]);
        }
    }
}
