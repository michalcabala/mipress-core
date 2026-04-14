<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Models\Entry;

class PublishScheduledEntries extends Command
{
    protected $signature = 'entries:publish-scheduled';

    protected $description = 'Publish all scheduled entries whose publish date has passed';

    public function handle(): int
    {
        /** @var Collection<int, Entry> $entries */
        $entries = Entry::query()
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

        if ($entries->isEmpty()) {
            $this->info('No scheduled entries to publish.');

            return self::SUCCESS;
        }

        foreach ($entries as $entry) {
            /** @var Entry $entry */
            $oldStatus = $entry->status;

            $entry->status = ContentStatus::Published;
            $entry->published_at = $entry->published_at?->isFuture() ? now() : ($entry->published_at ?? now());
            $entry->scheduled_at = null;
            $entry->save();

            if ($entry->author !== null && Schema::hasTable('notifications')) {
                Notification::make()
                    ->title('Položka byla publikována')
                    ->body('Položka "'.$entry->title.'" byla automaticky publikována podle plánu.')
                    ->success()
                    ->sendToDatabase($entry->author);
            }
        }

        $this->info("Published {$entries->count()} scheduled entries.");

        return self::SUCCESS;
    }
}
