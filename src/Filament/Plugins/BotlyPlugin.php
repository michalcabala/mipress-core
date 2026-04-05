<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Plugins;

use Filament\Panel;
use MiPress\Core\Filament\Pages\BotlyPage;

class BotlyPlugin extends \Awcodes\Botly\BotlyPlugin
{
    public function register(Panel $panel): void
    {
        $panel->pages([
            BotlyPage::class,
        ]);
    }
}
