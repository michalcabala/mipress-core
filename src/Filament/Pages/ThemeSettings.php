<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use MiPress\Core\Theme\ThemeManager;
use MiPress\Core\Theme\ThemeManifest;

class ThemeSettings extends Page
{
    protected string $view = 'mipress::filament.pages.theme-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'fal-palette';

    protected static ?string $navigationLabel = 'Témata';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Správa témat';

    /**
     * @return Collection<int, ThemeManifest>
     */
    public function getThemes(): Collection
    {
        return app(ThemeManager::class)->discover();
    }

    public function getActiveTheme(): string
    {
        return app(ThemeManager::class)->getActive();
    }

    public function activateTheme(string $slug): void
    {
        try {
            app(ThemeManager::class)->activate($slug);

            Notification::make()
                ->title('Téma aktivováno')
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Chyba aktivace tématu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
