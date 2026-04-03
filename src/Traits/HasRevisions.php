<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

            $model->createRevision();
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('created_at');
    }

    public function createRevision(?string $note = null): Revision
    {
        return Revision::create([
            'revisionable_type' => $this->getMorphClass(),
            'revisionable_id' => $this->getKey(),
            'user_id' => auth()->id(),
            'data' => $this->getRevisionSnapshot(),
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

        $this->createRevision('Obnoveno z revize #' . $revisionId);

        return $this;
    }

    protected function getRevisionSnapshot(): array
    {
        $excluded = ['id', 'created_at', 'updated_at', 'deleted_at'];

        return collect($this->getAttributes())
            ->except($excluded)
            ->toArray();
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
