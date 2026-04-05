<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Widgets;

use Illuminate\Support\Facades\Schema;
use MiPress\Core\Filament\Pages\SitemapSettingsPage;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;

class SitemapOverviewAlertsWidget extends \MuhammadNawlo\FilamentSitemapGenerator\Widgets\SitemapOverviewAlertsWidget
{
    public function getViewData(): array
    {
        $settingsTableExists = Schema::hasTable('sitemap_settings');
        $hasStaticUrls = false;
        $hasModels = false;
        $autoGenerationEnabled = false;

        if ($settingsTableExists) {
            try {
                $settings = SitemapSetting::getSettings();
                $staticUrls = $settings->static_urls;
                $models = $settings->models;
                $hasStaticUrls = is_array($staticUrls) && count(array_filter($staticUrls, fn ($url): bool => ! empty($url['url'] ?? ''))) > 0;
                $hasModels = is_array($models) && count(array_filter($models, fn ($model): bool => ! empty($model['model_class'] ?? '') && ! empty($model['enabled'] ?? true))) > 0;
                $autoGenerationEnabled = $settings->isAutoGenerationEnabled();
            } catch (\Throwable) {
            }
        } else {
            $config = config('filament-sitemap-generator', []);
            $hasStaticUrls = ! empty($config['static_urls']);
            $hasModels = ! empty($config['models']);
            $autoGenerationEnabled = (bool) ($config['schedule']['enabled'] ?? false);
        }

        return [
            'settingsUrl' => SitemapSettingsPage::getUrl(),
            'showNoStaticUrlsWarning' => ! $hasStaticUrls,
            'showNoModelsWarning' => ! $hasModels,
            'autoGenerationEnabled' => $autoGenerationEnabled,
        ];
    }
}
