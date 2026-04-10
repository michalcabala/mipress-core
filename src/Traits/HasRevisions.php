<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Revision;

trait HasRevisions
{
    protected bool $skipNextRevision = false;

    protected static function bootHasRevisions(): void
    {
        static::updating(function (Model $model): void {
            if ($model->skipNextRevision) {
                return;
            }

            $model->createRevision(snapshot: $model->getOriginal());
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    public function createRevision(?string $note = null, ?array $snapshot = null): Revision
    {
        return Revision::create([
            'revisionable_type' => $this->getMorphClass(),
            'revisionable_id' => $this->getKey(),
            'user_id' => auth()->id() ?: null,
            'data' => $this->getRevisionSnapshot($snapshot),
            'note' => $note,
        ]);
    }

    public function restoreRevision(int $revisionId): static
    {
        $revision = Revision::where('revisionable_type', $this->getMorphClass())
            ->where('revisionable_id', $this->getKey())
            ->findOrFail($revisionId);

        $restorable = collect($revision->data)
            ->only($this->getRestorableAttributes())
            ->toArray();

        $this->skipNextRevision = true;
        $this->fill($restorable);
        $this->save();
        $this->skipNextRevision = false;

        $this->createRevision('Obnoveno z revize #'.$revisionId);

        return $this;
    }

    protected function getRevisionSnapshot(?array $snapshot = null): array
    {
        $excluded = ['id', 'created_at', 'updated_at', 'deleted_at'];
        $source = $snapshot ?? $this->getAttributes();

        return collect($source)
            ->except($excluded)
            ->toArray();
    }

    public function latestPublishedRevisionSnapshot(): ?array
    {
        $revisions = $this->relationLoaded('revisions')
            ? $this->getRelation('revisions')
            : $this->revisions()->get();

        $revision = $revisions->first(
            fn (Revision $revision): bool => $this->normalizeRevisionStatus(data_get($revision->data, 'status')) === EntryStatus::Published->value,
        );

        if (! $revision instanceof Revision || ! is_array($revision->data)) {
            return null;
        }

        return $revision->data;
    }

    public function resolvePublicVersion(): static
    {
        if (method_exists($this, 'isPublished') && $this->isPublished()) {
            return $this;
        }

        $snapshot = $this->latestPublishedRevisionSnapshot();

        if ($snapshot === null) {
            return $this;
        }

        $publicVersion = clone $this;

        $publicVersion->forceFill(
            collect($snapshot)
                ->only($this->getFillable())
                ->toArray(),
        );
        $publicVersion->syncOriginal();

        return $publicVersion;
    }

    private function normalizeRevisionStatus(mixed $status): ?string
    {
        if ($status instanceof EntryStatus) {
            return $status->value;
        }

        if (! is_string($status)) {
            return null;
        }

        $candidate = strtolower(trim($status));

        return match (true) {
            str_contains($candidate, EntryStatus::Published->value) => EntryStatus::Published->value,
            str_contains($candidate, EntryStatus::Scheduled->value) => EntryStatus::Scheduled->value,
            str_contains($candidate, EntryStatus::InReview->value) => EntryStatus::InReview->value,
            str_contains($candidate, EntryStatus::Rejected->value) => EntryStatus::Rejected->value,
            str_contains($candidate, EntryStatus::Draft->value) => EntryStatus::Draft->value,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function getRestorableAttributes(): array
    {
        return collect($this->getFillable())
            ->reject(fn (string $attr): bool => in_array($attr, ['slug', 'author_id', 'locale', 'origin_id'], true))
            ->values()
            ->toArray();
    }
}
