<?php

declare(strict_types=1);

namespace MiPress\Core;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use MiPress\Core\Console\Commands\PublishScheduledEntries;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Media;
use MiPress\Core\Policies\BlueprintPolicy;
use MiPress\Core\Policies\CollectionPolicy;
use MiPress\Core\Policies\EntryPolicy;
use MiPress\Core\Policies\MediaPolicy;
use MiPress\Core\View\Components\MiPressImage;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mipress');

        $this->callAfterResolving('blade.compiler', function (): void {
            Blade::component('mipress-image', MiPressImage::class);
        });

        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Blueprint::class, BlueprintPolicy::class);
        Gate::policy(Media::class, MediaPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishScheduledEntries::class,
            ]);
        }
    }
}
