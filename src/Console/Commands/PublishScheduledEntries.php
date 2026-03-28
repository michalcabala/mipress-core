<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Illuminate\Console\Command;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;

class PublishScheduledEntries extends Command
{
    protected $signature = 'entries:publish-scheduled';

    protected $description = 'Publish all scheduled entries whose publish date has passed';

    public function handle(): int
    {
        $entries = Entry::query()
            ->where('status', EntryStatus::Scheduled->value)
            ->where('published_at', '<=', now())
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No scheduled entries to publish.');

            return self::SUCCESS;
        }

        foreach ($entries as $entry) {
            $oldStatus = $entry->status;

            $entry->status = EntryStatus::Published;
            $entry->save();

            AuditLog::logStatusChange($entry, EntryStatus::Published, $oldStatus, 'Automaticky publikováno plánovačem.');
        }

        $this->info("Published {$entries->count()} scheduled entries.");

        return self::SUCCESS;
    }
}
