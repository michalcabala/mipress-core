<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Illuminate\Console\Command;
use MiPress\Core\Services\SitemapGenerator;

class GenerateSitemap extends Command
{
    protected $signature = 'mipress:generate-sitemap';

    protected $description = 'Generate the sitemap.xml file for the site';

    public function handle(SitemapGenerator $generator): int
    {
        $this->info('Generating sitemap...');

        $result = $generator->generate();

        $this->info("Sitemap generated: {$result['urls']} URLs in {$result['files']} file(s).");

        return self::SUCCESS;
    }
}
