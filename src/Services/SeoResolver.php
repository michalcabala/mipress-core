<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Media;
use MiPress\Core\Models\Page;

class SeoResolver
{
    public function __construct(private readonly GlobalSeoSettingsManager $settingsManager) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function resolve(array $context = []): array
    {
        $resource = $context['resource'] ?? $context['entry'] ?? $context['page'] ?? null;
        $resource = $resource instanceof Entry || $resource instanceof Page ? $resource : null;

        $collection = $context['collection'] ?? null;
        $collection = $collection instanceof Collection ? $collection : null;

        $settings = $this->settingsManager->all(is_array($context['settings'] ?? null) ? $context['settings'] : null);
        $requestedUrl = $this->normalizeString($context['url'] ?? request()?->fullUrl());
        $isPreview = (bool) ($context['isPreview'] ?? false);
        $titleIsFinal = (bool) ($context['title_is_final'] ?? true);
        $isHome = $this->isHome($requestedUrl);
        $siteName = $this->resolveSiteName($settings);
        $title = $this->resolveTitle(
            resource: $resource,
            collection: $collection,
            settings: $settings,
            explicitTitle: $this->normalizeString($context['title'] ?? null),
            siteName: $siteName,
            isHome: $isHome,
            titleIsFinal: $titleIsFinal,
        );
        $description = $this->resolveDescription(
            resource: $resource,
            collection: $collection,
            settings: $settings,
            explicitDescription: $this->normalizeString($context['description'] ?? null),
            isHome: $isHome,
        );
        $canonicalUrl = $this->resolveCanonicalUrl(
            resource: $resource,
            requestedUrl: $requestedUrl,
            settings: $settings,
            isPreview: $isPreview,
        );
        [$imageUrl, $imageAlt] = $this->resolveImage(
            resource: $resource,
            settings: $settings,
            fallbackTitle: $title,
        );
        $htmlLang = $this->resolveHtmlLang($resource, $settings);
        $ogLocale = $this->resolveOgLocale($settings, $htmlLang);
        $alternateOgLocales = collect($settings['locale']['alternate_og_locales'] ?? [])
            ->filter(fn (mixed $locale): bool => is_string($locale) && trim($locale) !== '')
            ->reject(fn (string $locale): bool => $locale === $ogLocale)
            ->values()
            ->all();

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'html_lang' => $htmlLang,
            'og_locale' => $ogLocale,
            'alternate_og_locales' => $alternateOgLocales,
            'site_name' => $siteName,
            'og_type' => $resource instanceof Entry ? 'article' : 'website',
            'image_url' => $imageUrl,
            'image_alt' => $imageAlt,
            'twitter_card' => $imageUrl === null ? 'summary' : ($settings['twitter']['card'] ?? 'summary_large_image'),
            'twitter_site' => $this->normalizeTwitterHandle($settings['twitter']['site'] ?? null),
            'twitter_creator' => $this->normalizeTwitterHandle($settings['twitter']['creator'] ?? null),
            'verification' => array_filter([
                'google-site-verification' => $settings['verification']['google'] ?? null,
                'msvalidate.01' => $settings['verification']['bing'] ?? null,
                'seznam-wmt' => $settings['verification']['seznam'] ?? null,
                'facebook-domain-verification' => $settings['verification']['facebook_domain'] ?? null,
            ]),
            'structured_data' => $this->buildStructuredData(
                resource: $resource,
                collection: $collection,
                settings: $settings,
                title: $title,
                description: $description,
                canonicalUrl: $canonicalUrl,
                siteName: $siteName,
                imageUrl: $imageUrl,
                htmlLang: $htmlLang,
            ),
            'analytics' => [
                'google_analytics_id' => $settings['analytics']['google_analytics_id'] ?? null,
                'google_tag_manager_id' => $settings['analytics']['google_tag_manager_id'] ?? null,
            ],
            'published_time' => $resource?->published_at?->toAtomString(),
            'modified_time' => $resource?->updated_at?->toAtomString(),
            'noindex' => $isPreview,
            'is_preview' => $isPreview,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveSiteName(array $settings): string
    {
        return $this->normalizeString($settings['open_graph']['site_name'] ?? null)
            ?? $this->normalizeString(settings('general', 'site_name'))
            ?? $this->normalizeString(config('app.name'))
            ?? 'Web';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveTitle(Entry|Page|null $resource, ?Collection $collection, array $settings, ?string $explicitTitle, string $siteName, bool $isHome, bool $titleIsFinal): string
    {
        if ($isHome && filled($settings['metadata']['homepage_title'] ?? null)) {
            return $settings['metadata']['homepage_title'];
        }

        if ($resource instanceof Entry || $resource instanceof Page) {
            $baseTitle = $resource->getSeoTitle();

            if (filled($baseTitle)) {
                return $this->appendTitleSuffix($baseTitle, $settings['metadata']['title_suffix'] ?? null);
            }
        }

        if (filled($explicitTitle)) {
            return $titleIsFinal
                ? $explicitTitle
                : $this->appendTitleSuffix($explicitTitle, $settings['metadata']['title_suffix'] ?? null);
        }

        if ($collection instanceof Collection && filled($collection->name)) {
            return $this->appendTitleSuffix($collection->name, $settings['metadata']['title_suffix'] ?? null);
        }

        if (filled($settings['metadata']['default_title'] ?? null)) {
            return $this->appendTitleSuffix($settings['metadata']['default_title'], $settings['metadata']['title_suffix'] ?? null);
        }

        return $siteName;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveDescription(Entry|Page|null $resource, ?Collection $collection, array $settings, ?string $explicitDescription, bool $isHome): string
    {
        if ($resource instanceof Entry || $resource instanceof Page) {
            $resourceDescription = $resource->getSeoDescription();

            if (filled($resourceDescription)) {
                return $resourceDescription;
            }
        }

        if (filled($explicitDescription)) {
            return $explicitDescription;
        }

        if ($collection instanceof Collection && filled($collection->description)) {
            return trim($collection->description);
        }

        if ($isHome && filled(settings('general', 'site_description'))) {
            return trim((string) settings('general', 'site_description'));
        }

        if (filled($settings['metadata']['default_description'] ?? null)) {
            return $settings['metadata']['default_description'];
        }

        return trim((string) (settings('general', 'site_description', '') ?? ''));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveCanonicalUrl(Entry|Page|null $resource, ?string $requestedUrl, array $settings, bool $isPreview): ?string
    {
        $publicUrl = $resource instanceof Entry || $resource instanceof Page
            ? $this->normalizeString($resource->getPublicUrl())
            : null;

        $candidate = $isPreview && $publicUrl !== null
            ? $publicUrl
            : ($requestedUrl ?? $publicUrl);

        if ($candidate === null) {
            return null;
        }

        $parsed = $this->parseUrl($candidate);
        $baseUrl = $this->resolveBaseUrl($settings, $parsed['root'] ?? null);

        if ($baseUrl === null) {
            return null;
        }

        $path = $this->normalizePath($parsed['path'] ?? '/');
        $path = $this->applyTrailingSlash($path, $settings['canonical']['trailing_slash'] ?? 'keep');

        $canonical = rtrim($baseUrl, '/').($path === '/' ? '/' : $path);

        if (! ($settings['canonical']['strip_query_parameters'] ?? true) && filled($parsed['query'] ?? null)) {
            $canonical .= '?'.$parsed['query'];
        }

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveImage(Entry|Page|null $resource, array $settings, string $fallbackTitle): array
    {
        if ($resource instanceof Entry || $resource instanceof Page) {
            $imageUrl = $resource->getSeoImageUrl();

            if (filled($imageUrl)) {
                return [$imageUrl, $resource->getSeoImageAlt()];
            }
        }

        $imageId = $settings['open_graph']['default_image_id']
            ?? settings('general', 'logo');

        if (! is_numeric($imageId)) {
            return [null, null];
        }

        $media = Media::query()->find((int) $imageId);

        if (! $media instanceof Media) {
            return [null, null];
        }

        return [
            mipress_media_url($media, 'og'),
            $this->normalizeString($settings['open_graph']['default_image_alt'] ?? null)
                ?? $this->normalizeString($media->alt)
                ?? $fallbackTitle,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveHtmlLang(Entry|Page|null $resource, array $settings): string
    {
        $locale = $resource instanceof Entry || $resource instanceof Page
            ? $resource->getSeoLocale()
            : null;

        $locale ??= $this->normalizeString($settings['locale']['html_lang'] ?? null);
        $locale ??= $this->normalizeString(app()->getLocale());

        return str_replace('_', '-', $locale ?? 'cs');
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveOgLocale(array $settings, string $htmlLang): string
    {
        $configured = $this->normalizeString($settings['locale']['og_locale'] ?? null);

        if ($configured !== null) {
            return str_replace('-', '_', $configured);
        }

        $normalized = str_replace('-', '_', $htmlLang);

        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $normalized) === 1) {
            return $normalized;
        }

        return match (strtolower(substr($htmlLang, 0, 2))) {
            'cs' => 'cs_CZ',
            'sk' => 'sk_SK',
            'de' => 'de_DE',
            default => 'en_US',
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function buildStructuredData(Entry|Page|null $resource, ?Collection $collection, array $settings, string $title, string $description, ?string $canonicalUrl, string $siteName, ?string $imageUrl, string $htmlLang): array
    {
        if (! ($settings['structured_data']['enabled'] ?? false)) {
            return [];
        }

        $baseUrl = $this->resolveBaseUrl($settings, $canonicalUrl !== null ? $this->parseUrl($canonicalUrl)['root'] ?? null : null);
        $organizationName = $this->normalizeString($settings['structured_data']['organization_name'] ?? null)
            ?? $this->normalizeString(settings('general', 'site_name'))
            ?? $siteName;
        $organizationUrl = $this->normalizeString($settings['structured_data']['organization_url'] ?? null)
            ?? $baseUrl;

        $schemas = [];

        $schemas[] = $this->stripNulls([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $siteName,
            'url' => $baseUrl,
            'description' => $description,
            'inLanguage' => $htmlLang,
        ]);

        $organization = $this->stripNulls([
            '@context' => 'https://schema.org',
            '@type' => $settings['structured_data']['organization_type'] ?? 'Organization',
            'name' => $organizationName,
            'url' => $organizationUrl,
            'logo' => $this->resolveStructuredDataLogo($settings),
            'sameAs' => $settings['structured_data']['same_as'] ?? [],
            'email' => $settings['structured_data']['email'] ?? null,
            'telephone' => $settings['structured_data']['phone'] ?? null,
            'address' => $this->resolveStructuredDataAddress($settings),
        ]);

        if ($organization !== []) {
            $schemas[] = $organization;
        }

        if ($resource instanceof Entry) {
            $resource->loadMissing('author');

            $schemas[] = $this->stripNulls([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $title,
                'description' => $description,
                'url' => $canonicalUrl,
                'image' => $imageUrl !== null ? [$imageUrl] : null,
                'datePublished' => $resource->published_at?->toAtomString(),
                'dateModified' => $resource->updated_at?->toAtomString(),
                'inLanguage' => $htmlLang,
                'author' => filled($resource->author?->name)
                    ? [
                        '@type' => 'Person',
                        'name' => $resource->author?->name,
                    ]
                    : null,
                'publisher' => filled($organizationName)
                    ? [
                        '@type' => $settings['structured_data']['organization_type'] ?? 'Organization',
                        'name' => $organizationName,
                    ]
                    : null,
            ]);

            return array_values(array_filter($schemas));
        }

        $schemas[] = $this->stripNulls([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonicalUrl,
            'inLanguage' => $htmlLang,
            'isPartOf' => $baseUrl !== null
                ? [
                    '@type' => 'WebSite',
                    'url' => $baseUrl,
                    'name' => $siteName,
                ]
                : null,
            'about' => $collection instanceof Collection && filled($collection->name)
                ? [
                    '@type' => 'Thing',
                    'name' => $collection->name,
                ]
                : null,
        ]);

        return array_values(array_filter($schemas));
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveStructuredDataLogo(array $settings): ?string
    {
        $logoId = $settings['structured_data']['logo_id']
            ?? settings('general', 'logo');

        if (! is_numeric($logoId)) {
            return null;
        }

        return mipress_media_url(Media::query()->find((int) $logoId));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>|null
     */
    private function resolveStructuredDataAddress(array $settings): ?array
    {
        $address = $this->stripNulls([
            '@type' => 'PostalAddress',
            'streetAddress' => $settings['structured_data']['street_address'] ?? null,
            'addressLocality' => $settings['structured_data']['address_locality'] ?? null,
            'postalCode' => $settings['structured_data']['postal_code'] ?? null,
            'addressCountry' => $settings['structured_data']['address_country'] ?? null,
        ]);

        return $address === [] ? null : $address;
    }

    private function appendTitleSuffix(string $title, mixed $suffix): string
    {
        $suffix = $this->normalizeTitleSuffix($suffix);

        if ($suffix === null || str($title)->endsWith($suffix)) {
            return trim($title);
        }

        return trim($title.$suffix);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveBaseUrl(array $settings, ?string $fallbackRoot = null): ?string
    {
        $baseUrl = $this->normalizeString($settings['canonical']['base_url'] ?? null);

        if ($baseUrl !== null && filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            $baseUrl = null;
        }

        $baseUrl ??= $this->normalizeString(config('app.url'));
        $baseUrl ??= $fallbackRoot;
        $baseUrl ??= request()?->getSchemeAndHttpHost();

        if ($baseUrl === null) {
            return null;
        }

        if (($settings['canonical']['force_https'] ?? false) === true) {
            $baseUrl = preg_replace('/^http:\/\//i', 'https://', $baseUrl) ?? $baseUrl;
        }

        return rtrim($baseUrl, '/');
    }

    /**
     * @return array{root: ?string, path: string, query: ?string}
     */
    private function parseUrl(string $url): array
    {
        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return [
                'root' => null,
                'path' => $this->normalizePath($url),
                'query' => null,
            ];
        }

        $parts = parse_url($url);

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return [
            'root' => $scheme !== null && $host !== null ? $scheme.'://'.$host.$port : null,
            'path' => $this->normalizePath($parts['path'] ?? '/'),
            'query' => isset($parts['query']) && $parts['query'] !== '' ? $parts['query'] : null,
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        return preg_replace('#/+#', '/', $path) ?? $path;
    }

    private function applyTrailingSlash(string $path, string $mode): string
    {
        if ($path === '/') {
            return '/';
        }

        return match ($mode) {
            'add' => rtrim($path, '/').'/',
            'remove' => rtrim($path, '/'),
            default => $path,
        };
    }

    private function isHome(?string $url): bool
    {
        if ($url === null) {
            return request()?->path() === '/';
        }

        $path = str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? ($this->parseUrl($url)['path'] ?? '/')
            : $this->normalizePath($url);

        return $path === '/';
    }

    private function normalizeTwitterHandle(mixed $value): ?string
    {
        $value = $this->normalizeString($value);

        if ($value === null) {
            return null;
        }

        return str_starts_with($value, '@') ? $value : '@'.$value;
    }

    private function normalizeTitleSuffix(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = rtrim((string) $value);

        return trim($value) === '' ? null : $value;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function stripNulls(mixed $value): mixed
    {
        if (is_array($value)) {
            $filtered = [];

            foreach ($value as $key => $item) {
                $item = $this->stripNulls($item);

                if ($item === null || $item === []) {
                    continue;
                }

                $filtered[$key] = $item;
            }

            return $filtered;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
