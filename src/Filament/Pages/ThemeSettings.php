<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Clusters\WebCluster;
use MiPress\Core\Theme\ThemeManager;
use MiPress\Core\Theme\ThemeManifest;

class ThemeSettings extends Page
{
    protected string $view = 'mipress::filament.pages.theme-settings';

    protected static ?string $cluster = WebCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-palette';

    protected static ?string $navigationLabel = 'Témata';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Správa témat';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }

    /** @return Collection<int, ThemeManifest> */
    public function getThemes(): Collection
    {
        return app(ThemeManager::class)->discover();
    }

    public function getActiveTheme(): string
    {
        return app(ThemeManager::class)->getActive();
    }

    public function getActiveThemeManifest(): ?ThemeManifest
    {
        return $this->getThemes()->firstWhere('slug', $this->getActiveTheme());
    }

    /** @return Collection<int, ThemeManifest> */
    public function getInactiveThemes(): Collection
    {
        $active = $this->getActiveTheme();

        return $this->getThemes()->reject(fn (ThemeManifest $t): bool => $t->slug === $active)->values();
    }

    public function activateTheme(string $slug): void
    {
        try {
            app(ThemeManager::class)->activate($slug);

            Notification::make()
                ->title('Téma aktivováno')
                ->body('Aktivní téma bylo změněno na "'.$slug.'".')
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
