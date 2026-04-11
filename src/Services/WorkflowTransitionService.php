<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\EntryStatus;

class WorkflowTransitionService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareFormDataForStatus(array $data, bool $canPublish, ?EntryStatus $currentStatus = null): array
    {
        $selectedStatus = $this->normalizeStatus(data_get($data, 'status')) ?? $currentStatus ?? EntryStatus::Draft;

        if (! $canPublish) {
            if (in_array($currentStatus, [EntryStatus::Published, EntryStatus::Scheduled], true)) {
                return $this->prepareReviewData($data);
            }

            return $selectedStatus === EntryStatus::InReview
                ? $this->prepareCreateReviewData($data)
                : $this->prepareDraftData($data);
        }

        return match ($selectedStatus) {
            EntryStatus::Published, EntryStatus::Scheduled => $this->preparePublishData($data),
            EntryStatus::InReview => $this->prepareCreateReviewData($data),
            EntryStatus::Rejected => $this->prepareRejectedData($data),
            default => $this->prepareDraftData($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareCreateDataForIntent(array $data, string $intent): array
    {
        $data['review_note'] = null;

        return match ($intent) {
            'review' => $this->prepareCreateReviewData($data),
            'publish' => $this->preparePublishData($data),
            default => $this->prepareDraftData($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareReviewData(array $data): array
    {
        $data['status'] = EntryStatus::InReview;
        $data['scheduled_at'] = null;

        $publishedAt = $this->normalizeDate(data_get($data, 'published_at'));

        if ($publishedAt?->isFuture() === true) {
            $data['published_at'] = null;
        }

        $data['review_note'] = null;

        return $data;
    }

    public function saveDraft(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Draft, [
            'review_note' => null,
        ]);
    }

    public function transitionToReview(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::InReview, [
            'review_note' => null,
        ]);
    }

    public function reject(Model $record, string $reason): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Rejected, [
            'review_note' => $reason,
        ]);
    }

    public function publish(Model $record): WorkflowTransitionResult
    {
        $scheduleAt = $record->scheduled_at ?? $record->published_at;

        if ($scheduleAt instanceof CarbonInterface && $scheduleAt->isFuture()) {
            return $this->schedule($record, $scheduleAt);
        }

        return $this->transition($record, EntryStatus::Published, [
            'published_at' => $record->published_at ?? now(),
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function unpublish(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Draft, [
            'review_note' => null,
        ]);
    }

    public function cancelSchedule(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Draft, [
            'published_at' => null,
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function publishNow(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Published, [
            'published_at' => now(),
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function schedule(Model $record, CarbonInterface $scheduleAt): WorkflowTransitionResult
    {
        return $this->transition($record, EntryStatus::Scheduled, [
            'published_at' => $scheduleAt,
            'scheduled_at' => $scheduleAt,
            'review_note' => null,
        ], $scheduleAt);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Model $record, EntryStatus $newStatus, array $attributes = [], ?CarbonInterface $scheduledFor = null): WorkflowTransitionResult
    {
        /** @var EntryStatus $oldStatus */
        $oldStatus = $record->status;

        $record->status = $newStatus;

        foreach ($attributes as $attribute => $value) {
            $record->{$attribute} = $value;
        }

        $record->save();

        return new WorkflowTransitionResult($oldStatus, $newStatus, $scheduledFor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareDraftData(array $data): array
    {
        $data['status'] = EntryStatus::Draft;
        $data['scheduled_at'] = null;

        $publishedAt = $this->normalizeDate(data_get($data, 'published_at'));

        if ($publishedAt?->isFuture() === true) {
            $data['published_at'] = null;
        }

        $data['review_note'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareRejectedData(array $data): array
    {
        $data['status'] = EntryStatus::Rejected;
        $data['scheduled_at'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePublishData(array $data): array
    {
        $scheduledAt = $this->normalizeDate(data_get($data, 'scheduled_at'));
        $publishedAt = $this->normalizeDate(data_get($data, 'published_at'));
        $scheduleAt = $scheduledAt ?: $publishedAt;

        if ($scheduleAt instanceof CarbonInterface && now()->lt($scheduleAt)) {
            $data['status'] = EntryStatus::Scheduled;
            $data['scheduled_at'] = $scheduleAt;
            $data['published_at'] = $scheduleAt;

            return $data;
        }

        $data['status'] = EntryStatus::Published;
        $data['published_at'] = $publishedAt ?: now();
        $data['scheduled_at'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareCreateReviewData(array $data): array
    {
        return $this->prepareReviewData($data);
    }

    private function normalizeDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private function normalizeStatus(mixed $value): ?EntryStatus
    {
        if ($value instanceof EntryStatus) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return EntryStatus::tryFrom($value);
    }
}
