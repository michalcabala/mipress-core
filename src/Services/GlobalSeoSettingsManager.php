<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use MiPress\Core\Models\Setting;

class GlobalSeoSettingsManager
{
    public const HANDLE = 'seo';

    /**
     * @param  array<string, mixed>|null  $overrides
     * @return array<string, mixed>
     */
    public function all(?array $overrides = null): array
    {
        $stored = settings(self::HANDLE);
        $settings = $this->normalize(is_array($stored) ? $stored : []);

        if (! is_array($overrides) || $overrides === []) {
            return $settings;
        }

        return array_replace_recursive($settings, $this->normalize($overrides, mergeWithDefaults: false));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): Setting
    {
        $setting = Setting::query()->firstOrCreate(
            ['handle' => self::HANDLE],
            [
                'name' => 'SEO',
                'icon' => 'fal-magnifying-glass',
                'sort_order' => 40,
                'data' => [],
            ],
        );

        $setting->forceFill([
            'name' => 'SEO',
            'icon' => 'fal-magnifying-glass',
            'sort_order' => 40,
            'data' => $this->compact($this->normalize($data, mergeWithDefaults: false)),
        ]);

        $setting->save();

        return $setting;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return list<string>
     */
    public function warnings(?array $data = null): array
    {
        $settings = $data === null ? $this->all() : $this->all($data);
        $warnings = [];

        if (! filled($settings['metadata']['default_description'] ?? null)) {
            $warnings[] = 'Chybí výchozí meta popis pro stránky bez vlastního SEO popisu.';
        }

        if (filled($settings['canonical']['base_url'] ?? null) && ! filter_var($settings['canonical']['base_url'], FILTER_VALIDATE_URL)) {
            $warnings[] = 'Preferovaná canonical URL není validní absolutní adresa.';
        }

        if (! filled($settings['open_graph']['default_image_id'] ?? null)) {
            $warnings[] = 'Chybí výchozí OG obrázek pro sdílení stránek bez vlastního obrázku.';
        }

        if (filled($settings['analytics']['google_analytics_id'] ?? null) && filled($settings['analytics']['google_tag_manager_id'] ?? null)) {
            $warnings[] = 'GA4 i GTM jsou nastavené současně. Ověřte, že neměří stejnou návštěvu dvakrát.';
        }

        if (($settings['structured_data']['enabled'] ?? false) && ! filled($settings['structured_data']['organization_name'] ?? null) && ! filled(settings('general', 'site_name'))) {
            $warnings[] = 'Structured data nemají název organizace ani název webu v obecném nastavení.';
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalize(array $data, bool $mergeWithDefaults = true): array
    {
        if (filled($data['meta_title_suffix'] ?? null) && ! filled(data_get($data, 'metadata.title_suffix'))) {
            data_set($data, 'metadata.title_suffix', $data['meta_title_suffix']);
        }

        if (filled($data['meta_description'] ?? null) && ! filled(data_get($data, 'metadata.default_description'))) {
            data_set($data, 'metadata.default_description', $data['meta_description']);
        }

        $normalized = [
            'metadata' => [
                'default_title' => $this->normalizeString(data_get($data, 'metadata.default_title')),
                'homepage_title' => $this->normalizeString(data_get($data, 'metadata.homepage_title')),
                'title_suffix' => $this->normalizeTitleSuffix(data_get($data, 'metadata.title_suffix')),
                'default_description' => $this->normalizeString(data_get($data, 'metadata.default_description')),
            ],
            'canonical' => [
                'base_url' => $this->normalizeString(data_get($data, 'canonical.base_url')),
                'strip_query_parameters' => $this->normalizeBoolean(data_get($data, 'canonical.strip_query_parameters'), true),
                'force_https' => $this->normalizeBoolean(data_get($data, 'canonical.force_https'), false),
                'trailing_slash' => $this->normalizeOption(data_get($data, 'canonical.trailing_slash'), ['keep', 'add', 'remove'], 'keep'),
            ],
            'locale' => [
                'html_lang' => $this->normalizeString(data_get($data, 'locale.html_lang')),
                'og_locale' => $this->normalizeString(data_get($data, 'locale.og_locale')),
                'alternate_og_locales' => $this->normalizeList(data_get($data, 'locale.alternate_og_locales', [])),
            ],
            'open_graph' => [
                'site_name' => $this->normalizeString(data_get($data, 'open_graph.site_name')),
                'default_image_id' => $this->normalizeId(data_get($data, 'open_graph.default_image_id')),
                'default_image_alt' => $this->normalizeString(data_get($data, 'open_graph.default_image_alt')),
            ],
            'twitter' => [
                'card' => $this->normalizeOption(data_get($data, 'twitter.card'), ['summary', 'summary_large_image'], 'summary_large_image'),
                'site' => $this->normalizeString(data_get($data, 'twitter.site')),
                'creator' => $this->normalizeString(data_get($data, 'twitter.creator')),
            ],
            'structured_data' => [
                'enabled' => $this->normalizeBoolean(data_get($data, 'structured_data.enabled'), true),
                'organization_type' => $this->normalizeOption(data_get($data, 'structured_data.organization_type'), ['Organization', 'LocalBusiness'], 'Organization'),
                'organization_name' => $this->normalizeString(data_get($data, 'structured_data.organization_name')),
                'organization_url' => $this->normalizeString(data_get($data, 'structured_data.organization_url')),
                'logo_id' => $this->normalizeId(data_get($data, 'structured_data.logo_id')),
                'phone' => $this->normalizeString(data_get($data, 'structured_data.phone')),
                'email' => $this->normalizeString(data_get($data, 'structured_data.email')),
                'street_address' => $this->normalizeString(data_get($data, 'structured_data.street_address')),
                'address_locality' => $this->normalizeString(data_get($data, 'structured_data.address_locality')),
                'postal_code' => $this->normalizeString(data_get($data, 'structured_data.postal_code')),
                'address_country' => $this->normalizeString(data_get($data, 'structured_data.address_country')),
                'same_as' => $this->normalizeList(data_get($data, 'structured_data.same_as', [])),
            ],
            'verification' => [
                'google' => $this->normalizeString(data_get($data, 'verification.google')),
                'bing' => $this->normalizeString(data_get($data, 'verification.bing')),
                'seznam' => $this->normalizeString(data_get($data, 'verification.seznam')),
                'facebook_domain' => $this->normalizeString(data_get($data, 'verification.facebook_domain')),
            ],
            'analytics' => [
                'google_analytics_id' => $this->normalizeString(data_get($data, 'analytics.google_analytics_id')),
                'google_tag_manager_id' => $this->normalizeString(data_get($data, 'analytics.google_tag_manager_id')),
            ],
        ];

        if (! $mergeWithDefaults) {
            return $normalized;
        }

        return array_replace_recursive($this->defaults(), $normalized);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'metadata' => [
                'default_title' => null,
                'homepage_title' => null,
                'title_suffix' => null,
                'default_description' => null,
            ],
            'canonical' => [
                'base_url' => null,
                'strip_query_parameters' => true,
                'force_https' => false,
                'trailing_slash' => 'keep',
            ],
            'locale' => [
                'html_lang' => app()->getLocale(),
                'og_locale' => null,
                'alternate_og_locales' => [],
            ],
            'open_graph' => [
                'site_name' => null,
                'default_image_id' => null,
                'default_image_alt' => null,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'site' => null,
                'creator' => null,
            ],
            'structured_data' => [
                'enabled' => true,
                'organization_type' => 'Organization',
                'organization_name' => null,
                'organization_url' => null,
                'logo_id' => null,
                'phone' => null,
                'email' => null,
                'street_address' => null,
                'address_locality' => null,
                'postal_code' => null,
                'address_country' => 'CZ',
                'same_as' => [],
            ],
            'verification' => [
                'google' => null,
                'bing' => null,
                'seznam' => null,
                'facebook_domain' => null,
            ],
            'analytics' => [
                'google_analytics_id' => null,
                'google_tag_manager_id' => null,
            ],
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeTitleSuffix(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = rtrim((string) $value);

        return trim($value) === '' ? null : $value;
    }

    private function normalizeId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function normalizeList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_string($item) || is_numeric($item))
            ->map(fn (string|int|float $item): string => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function normalizeOption(mixed $value, array $allowed, string $default): string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return $default;
        }

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function compact(mixed $value): mixed
    {
        if (is_array($value)) {
            $compacted = [];

            foreach ($value as $key => $item) {
                $item = $this->compact($item);

                if ($item === null || $item === []) {
                    continue;
                }

                $compacted[$key] = $item;
            }

            return $compacted;
        }

        if (is_string($value)) {
            $value = rtrim($value);

            return trim($value) === '' ? null : $value;
        }

        return $value;
    }
}
