<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use MiPress\Core\Models\AuditLog;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('created', [], $model->getAuditAttributes());
        });

        static::updated(function (Model $model) {
            $old = $model->getAuditOriginal();
            $new = $model->getAuditChanges();

            if (! empty($new)) {
                $model->logAudit('updated', $old, $new);
            }
        });

        static::deleted(function (Model $model) {
            $model->logAudit('deleted');
        });

        static::restored(function (Model $model) {
            $model->logAudit('restored');
        });
    }

    public function logAudit(
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?string $note = null,
    ): void {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
            'note' => $note,
        ]);
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    protected function getAuditAttributes(): array
    {
        return collect($this->getAttributes())
            ->except($this->getAuditExcluded())
            ->toArray();
    }

    protected function getAuditOriginal(): array
    {
        $changed = array_keys($this->getChanges());

        return collect($this->getOriginal())
            ->except($this->getAuditExcluded())
            ->only($changed)
            ->toArray();
    }

    protected function getAuditChanges(): array
    {
        return collect($this->getChanges())
            ->except($this->getAuditExcluded())
            ->toArray();
    }

    protected function getAuditExcluded(): array
    {
        $modelExcludes = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return array_merge(['updated_at', 'created_at', 'deleted_at'], $modelExcludes);
    }
}
