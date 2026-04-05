<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use MiPress\Core\Filament\Clusters\SeoCluster;
use MiPress\Core\Filament\Widgets\SitemapOverviewAlertsWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapPreviewWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapRecentRunsWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapRunsTableWidget;
use MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapStatsWidget;

class SitemapGeneratorPage extends \MuhammadNawlo\FilamentSitemapGenerator\Pages\SitemapGeneratorPage
{
    protected static ?string $cluster = SeoCluster::class;

    protected static ?string $navigationLabel = 'Správa sitemapy';

    protected static ?int $navigationSort = 20;

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-sitemap';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->livewireProperty('activeTab')
                ->contained(false)
                ->tabs([
                    'overview' => Tab::make(__('filament-sitemap-generator::page.tab_overview'))
                        ->schema([
                            Livewire::make(SitemapOverviewAlertsWidget::class)->key('sitemap-overview-alerts'),
                            Grid::make(1)
                                ->schema([
                                    Livewire::make(SitemapStatsWidget::class)->key('sitemap-stats'),
                                    Livewire::make(SitemapRecentRunsWidget::class)->key('sitemap-recent-runs'),
                                ]),
                        ]),
                    'preview' => Tab::make(__('filament-sitemap-generator::page.tab_preview'))
                        ->schema([
                            Livewire::make(SitemapPreviewWidget::class)->key('sitemap-preview'),
                        ]),
                    'runs' => Tab::make(__('filament-sitemap-generator::page.tab_runs'))
                        ->schema([
                            Livewire::make(SitemapRunsTableWidget::class)->key('sitemap-runs-table'),
                        ]),
                ]),
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user() !== null && Gate::allows('viewAny', SitemapSetting::class);
    }
}
