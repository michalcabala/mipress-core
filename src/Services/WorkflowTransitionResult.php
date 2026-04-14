<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Carbon\CarbonInterface;
use MiPress\Core\Enums\ContentStatus;

final class WorkflowTransitionResult
{
    public function __construct(
        public readonly ContentStatus $oldStatus,
        public readonly ContentStatus $newStatus,
        public readonly ?CarbonInterface $scheduledFor = null,
    ) {}

    public function isScheduled(): bool
    {
        return $this->newStatus === ContentStatus::Scheduled;
    }
}
