<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\ContentStatus;

trait HasWorkflow
{
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $outer): void {
            $outer
                ->where(function (Builder $published): void {
                    $published->where('status', ContentStatus::Published)
                        ->where(function (Builder $q): void {
                            $q->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        });
                })
                ->orWhere('status', ContentStatus::InReview);
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published)
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Draft);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Scheduled)
            ->where(function (Builder $scheduled): void {
                $scheduled
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNotNull('scheduled_at')
                            ->where('scheduled_at', '>', now());
                    })
                    ->orWhere(function (Builder $legacy): void {
                        $legacy
                            ->whereNull('scheduled_at')
                            ->whereNotNull('published_at')
                            ->where('published_at', '>', now());
                    });
            });
    }

    public function scopeForStatus(Builder $query, ContentStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function publish(): static
    {
        $this->status = ContentStatus::Published;
        $this->published_at ??= now();
        $this->scheduled_at = null;
        $this->save();

        return $this;
    }

    public function unpublish(): static
    {
        $this->status = ContentStatus::Draft;
        $this->save();

        return $this;
    }

    public function submitForReview(): static
    {
        $this->status = ContentStatus::InReview;
        $this->save();

        return $this;
    }

    public function reject(string $reason): static
    {
        $this->status = ContentStatus::Rejected;
        $this->review_note = $reason;
        $this->save();

        return $this;
    }

    public function schedule(\DateTimeInterface $publishAt): static
    {
        $this->status = ContentStatus::Scheduled;
        $this->scheduled_at = $publishAt;
        $this->published_at = $publishAt;
        $this->save();

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    public function isDraft(): bool
    {
        return $this->status === ContentStatus::Draft;
    }

    public function isInReview(): bool
    {
        return $this->status === ContentStatus::InReview;
    }
}
