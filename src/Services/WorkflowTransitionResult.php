<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Carbon\CarbonInterface;
use MiPress\Core\Enums\EntryStatus;

final class WorkflowTransitionResult
{
    public function __construct(
        public readonly EntryStatus $oldStatus,
        public readonly EntryStatus $newStatus,
        public readonly ?CarbonInterface $scheduledFor = null,
    ) {}

    public function isScheduled(): bool
    {
        return $this->newStatus === EntryStatus::Scheduled;
    }
}
