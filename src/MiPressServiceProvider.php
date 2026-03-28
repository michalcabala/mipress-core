<?php

declare(strict_types=1);

namespace MiPress\Core;

use Illuminate\Support\ServiceProvider;

class MiPressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mipress');
        // $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
