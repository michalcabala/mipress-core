<?php

declare(strict_types=1);

namespace MiPress\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use MiPress\Core\Services\SitemapGenerator;

class GenerateSitemapJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sitemap-generation'))->dontRelease()->expireAfter(180),
        ];
    }

    public function handle(SitemapGenerator $generator): void
    {
        $result = $generator->generate();

        Log::info("Sitemap generated: {$result['urls']} URLs in {$result['files']} file(s).");
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }
}
