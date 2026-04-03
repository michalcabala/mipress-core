<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;

trait HasWorkflow
{
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer
                ->where(function (Builder $published): void {
                    $published->where('status', EntryStatus::Published)
                        ->where(function (Builder $q): void {
                            $q->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        });
                })
                ->orWhere('status', EntryStatus::InReview);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Published)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Draft);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Scheduled)
            ->where('published_at', '>', now());
    }

    public function scopeForStatus(Builder $query, EntryStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function publish(): static
    {
        $this->status = EntryStatus::Published;
        $this->published_at ??= now();
        $this->save();

        return $this;
    }

    public function unpublish(): static
    {
        $this->status = EntryStatus::Draft;
        $this->save();

        return $this;
    }

    public function submitForReview(): static
    {
        $this->status = EntryStatus::InReview;
        $this->save();

        return $this;
    }

    public function reject(string $reason): static
    {
        $this->status = EntryStatus::Rejected;
        $this->review_note = $reason;
        $this->save();

        return $this;
    }

    public function schedule(\DateTimeInterface $publishAt): static
    {
        $this->status = EntryStatus::Scheduled;
        $this->published_at = $publishAt;
        $this->save();

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === EntryStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isDraft(): bool
    {
        return $this->status === EntryStatus::Draft;
    }

    public function isInReview(): bool
    {
        return $this->status === EntryStatus::InReview;
    }
}
