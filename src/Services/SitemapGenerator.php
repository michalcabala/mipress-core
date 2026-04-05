<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;
use XMLWriter;

class SitemapGenerator
{
    private const MAX_URLS_PER_FILE = 50_000;

    /**
     * @return array{urls: int, files: int}
     */
    public function generate(): array
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        $staticUrls = $this->getStaticUrls();
        $modelUrls = $this->collectModelUrls($baseUrl);

        $allUrls = array_merge($staticUrls, $modelUrls);
        $totalUrls = count($allUrls);

        if ($totalUrls === 0) {
            $this->writeSingleSitemap($baseUrl, []);
            $this->updateLastGenerated($totalUrls);

            return ['urls' => 0, 'files' => 1];
        }

        if ($totalUrls <= self::MAX_URLS_PER_FILE) {
            $this->writeSingleSitemap($baseUrl, $allUrls);
            $this->updateLastGenerated($totalUrls);

            return ['urls' => $totalUrls, 'files' => 1];
        }

        $chunks = array_chunk($allUrls, self::MAX_URLS_PER_FILE);
        $fileCount = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $this->writeSitemapFile("sitemap-{$index}.xml", $chunk);
        }

        $this->writeSitemapIndex($baseUrl, $fileCount);
        $this->updateLastGenerated($totalUrls);

        return ['urls' => $totalUrls, 'files' => $fileCount];
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>
     */
    private function getStaticUrls(): array
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        $raw = Setting::getValue('sitemap.static_urls');

        if ($raw === null) {
            return [
                [
                    'loc' => $baseUrl . '/',
                    'lastmod' => now()->toDateString(),
                    'changefreq' => 'daily',
                    'priority' => '1.0',
                ],
            ];
        }

        $entries = json_decode($raw, true);

        if (! is_array($entries)) {
            return [];
        }

        return array_filter(array_map(function (array $entry) use ($baseUrl): ?array {
            $url = trim($entry['url'] ?? '');

            if ($url === '') {
                return null;
            }

            $loc = str_starts_with($url, 'http') ? $url : $baseUrl . '/' . ltrim($url, '/');

            return [
                'loc' => $loc,
                'lastmod' => null,
                'changefreq' => $entry['changefreq'] ?? 'weekly',
                'priority' => $entry['priority'] ?? '0.5',
            ];
        }, $entries));
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}>
     */
    private function collectModelUrls(string $baseUrl): array
    {
        $urls = [];
        $models = config('mipress.sitemap.models', []);

        foreach ($models as $modelClass => $options) {
            if (! class_exists($modelClass)) {
                Log::warning("Sitemap: model class {$modelClass} not found, skipping.");

                continue;
            }

            $priority = (string) ($options['priority'] ?? '0.5');
            $changefreq = $options['changefreq'] ?? 'weekly';

            $query = $modelClass::query();

            if (method_exists($modelClass, 'scopePublished')) {
                $query->published();
            } else {
                $query->where('status', EntryStatus::Published);
            }

            $query->select(['id', 'slug', 'updated_at', ...$this->extraSelectColumns($modelClass)])
                ->orderBy('id')
                ->chunkById(500, function ($records) use (&$urls, $baseUrl, $priority, $changefreq): void {
                    foreach ($records as $record) {
                        $url = method_exists($record, 'getPublicUrl')
                            ? $record->getPublicUrl()
                            : null;

                        if ($url === null || $url === '') {
                            continue;
                        }

                        $loc = str_starts_with($url, 'http') ? $url : $baseUrl . $url;

                        $urls[] = [
                            'loc' => $loc,
                            'lastmod' => $record->updated_at?->toDateString(),
                            'changefreq' => $changefreq,
                            'priority' => $priority,
                        ];
                    }
                });
        }

        return $urls;
    }

    /**
     * @return array<int, string>
     */
    private function extraSelectColumns(string $modelClass): array
    {
        return match (true) {
            is_a($modelClass, Entry::class, true) => ['title', 'collection_id', 'published_at', 'status'],
            is_a($modelClass, Page::class, true) => ['title', 'published_at', 'status', 'parent_id'],
            default => ['status'],
        };
    }

    /**
     * @param array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}> $urls
     */
    private function writeSingleSitemap(string $baseUrl, array $urls): void
    {
        $this->writeSitemapFile('sitemap.xml', $urls);
    }

    /**
     * @param array<int, array{loc: string, lastmod: string|null, changefreq: string, priority: string}> $urls
     */
    private function writeSitemapFile(string $filename, array $urls): void
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', $url['loc']);

            if ($url['lastmod'] !== null) {
                $writer->writeElement('lastmod', $url['lastmod']);
            }

            $writer->writeElement('changefreq', $url['changefreq']);
            $writer->writeElement('priority', $url['priority']);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        file_put_contents(public_path($filename), $writer->outputMemory());
    }

    private function writeSitemapIndex(string $baseUrl, int $fileCount): void
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $now = now()->toW3cString();

        for ($i = 0; $i < $fileCount; $i++) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $baseUrl . "/sitemap-{$i}.xml");
            $writer->writeElement('lastmod', $now);
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        file_put_contents(public_path('sitemap.xml'), $writer->outputMemory());
    }

    private function updateLastGenerated(int $urlCount): void
    {
        Setting::putValue('sitemap.last_generated_at', now()->toIso8601String());
        Setting::putValue('sitemap.last_url_count', (string) $urlCount);
    }
}
