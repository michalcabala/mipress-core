<?php

declare(strict_types=1);

namespace MiPress\Core\Observers;

use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Jobs\GenerateSitemapJob;
use MiPress\Core\Models\Setting;

class ContentObserver
{
    public function saved(Model $model): void
    {
        if (! $this->shouldRegenerate($model)) {
            return;
        }

        GenerateSitemapJob::dispatch();
    }

    public function deleted(Model $model): void
    {
        if (! $this->isAutoGenerateEnabled()) {
            return;
        }

        if ($model->getAttribute('status') !== EntryStatus::Published) {
            return;
        }

        GenerateSitemapJob::dispatch();
    }

    private function shouldRegenerate(Model $model): bool
    {
        if (! $this->isAutoGenerateEnabled()) {
            return false;
        }

        $status = $model->getAttribute('status');
        $originalStatus = $model->getOriginal('status');

        // Published → something else (unpublished)
        if ($originalStatus === EntryStatus::Published && $status !== EntryStatus::Published) {
            return true;
        }

        // Something else → Published (newly published)
        if ($status === EntryStatus::Published && $originalStatus !== EntryStatus::Published) {
            return true;
        }

        // Already published and slug changed
        if ($status === EntryStatus::Published && $model->wasChanged('slug')) {
            return true;
        }

        return false;
    }

    private function isAutoGenerateEnabled(): bool
    {
        return Setting::getValue('sitemap.enabled', '1') === '1'
            && Setting::getValue('sitemap.auto_generate', '1') === '1';
    }
}
