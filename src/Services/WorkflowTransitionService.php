<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\ContentStatus;

class WorkflowTransitionService
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepareFormDataForStatus(array $data, bool $canPublish, ?ContentStatus $currentStatus = null): array
    {
        $selectedStatus = $this->normalizeStatus(data_get($data, 'status')) ?? $currentStatus ?? ContentStatus::Draft;

        if (! $canPublish) {
            if (in_array($currentStatus, [ContentStatus::Published, ContentStatus::Scheduled], true)) {
                return $this->prepareReviewData($data);
            }

            return $selectedStatus === ContentStatus::InReview
                ? $this->prepareCreateReviewData($data)
                : $this->prepareDraftData($data);
        }

        return match ($selectedStatus) {
            ContentStatus::Published, ContentStatus::Scheduled => $this->preparePublishData($data),
            ContentStatus::InReview => $this->prepareCreateReviewData($data),
            ContentStatus::Rejected => $this->prepareRejectedData($data),
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
        $data['status'] = ContentStatus::InReview;
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
        return $this->transition($record, ContentStatus::Draft, [
            'review_note' => null,
        ]);
    }

    public function transitionToReview(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::InReview, [
            'review_note' => null,
        ]);
    }

    public function reject(Model $record, string $reason): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::Rejected, [
            'review_note' => $reason,
        ]);
    }

    public function publish(Model $record): WorkflowTransitionResult
    {
        $scheduleAt = $record->scheduled_at ?? $record->published_at;

        if ($scheduleAt instanceof CarbonInterface && $scheduleAt->isFuture()) {
            return $this->schedule($record, $scheduleAt);
        }

        return $this->transition($record, ContentStatus::Published, [
            'published_at' => $record->published_at ?? now(),
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function unpublish(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::Draft, [
            'review_note' => null,
        ]);
    }

    public function cancelSchedule(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::Draft, [
            'published_at' => null,
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function publishNow(Model $record): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::Published, [
            'published_at' => now(),
            'scheduled_at' => null,
            'review_note' => null,
        ]);
    }

    public function schedule(Model $record, CarbonInterface $scheduleAt): WorkflowTransitionResult
    {
        return $this->transition($record, ContentStatus::Scheduled, [
            'published_at' => $scheduleAt,
            'scheduled_at' => $scheduleAt,
            'review_note' => null,
        ], $scheduleAt);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Model $record, ContentStatus $newStatus, array $attributes = [], ?CarbonInterface $scheduledFor = null): WorkflowTransitionResult
    {
        /** @var ContentStatus $oldStatus */
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
        $data['status'] = ContentStatus::Draft;
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
        $data['status'] = ContentStatus::Rejected;
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
            $data['status'] = ContentStatus::Scheduled;
            $data['scheduled_at'] = $scheduleAt;
            $data['published_at'] = $scheduleAt;

            return $data;
        }

        $data['status'] = ContentStatus::Published;
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

    private function normalizeStatus(mixed $value): ?ContentStatus
    {
        if ($value instanceof ContentStatus) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return ContentStatus::tryFrom($value);
    }
}
