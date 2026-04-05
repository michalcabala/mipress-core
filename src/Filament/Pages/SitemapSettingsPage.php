<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use MiPress\Core\Filament\Clusters\SeoCluster;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;

class SitemapSettingsPage extends \MuhammadNawlo\FilamentSitemapGenerator\Pages\SitemapSettingsPage
{
    protected static ?string $cluster = SeoCluster::class;

    protected static ?string $navigationLabel = 'Nastavení sitemapy';

    protected static ?int $navigationSort = 21;

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-sliders';
    }

    public static function canAccess(): bool
    {
        return auth()->user() !== null && Gate::allows('viewAny', SitemapSetting::class);
    }
}
