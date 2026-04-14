<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Models\Page;

class PublishScheduledPages extends Command
{
    protected $signature = 'pages:publish-scheduled';

    protected $description = 'Publish all scheduled pages whose publish date has passed';

    public function handle(): int
    {
        /** @var Collection<int, Page> $pages */
        $pages = Page::query()
            ->where('status', ContentStatus::Scheduled->value)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $scheduled): void {
                        $scheduled
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '<=', now());
                    })
                    ->orWhere(function (Builder $legacy): void {
                        $legacy
                            ->whereNull('scheduled_at')
                            ->whereNotNull('published_at')
                            ->where('published_at', '<=', now());
                    });
            })
            ->get();

        if ($pages->isEmpty()) {
            $this->info('No scheduled pages to publish.');

            return self::SUCCESS;
        }

        foreach ($pages as $page) {
            /** @var Page $page */
            $page->status = ContentStatus::Published;
            $page->published_at = $page->published_at?->isFuture() ? now() : ($page->published_at ?? now());
            $page->scheduled_at = null;
            $page->save();

            if ($page->author !== null && Schema::hasTable('notifications')) {
                Notification::make()
                    ->title('Stránka byla publikována')
                    ->body('Stránka "'.$page->title.'" byla automaticky publikována podle plánu.')
                    ->success()
                    ->sendToDatabase($page->author);
            }
        }

        $this->info("Published {$pages->count()} scheduled pages.");

        return self::SUCCESS;
    }
}
