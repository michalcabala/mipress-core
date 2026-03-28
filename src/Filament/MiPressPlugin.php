<?php

declare(strict_types=1);

namespace MiPress\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
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
        $panel->resources([
            UserResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
