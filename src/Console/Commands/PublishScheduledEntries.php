<?php

declare(strict_types=1);

namespace MiPress\Core\Console\Commands;

use Illuminate\Console\Command;
use Filament\Notifications\Notification;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;
use Illuminate\Support\Facades\Schema;

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
