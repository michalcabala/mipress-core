<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'action',
        'old_values',
        'new_values',
        'note',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function logStatusChange(
        Model $model,
        mixed $newStatus,
        mixed $oldStatus,
        ?string $note = null,
    ): void {
        static::create([
            'user_id' => auth()->id(),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'action' => 'status_changed',
            'old_values' => ['status' => $oldStatus instanceof \BackedEnum ? $oldStatus->value : $oldStatus],
            'new_values' => ['status' => $newStatus instanceof \BackedEnum ? $newStatus->value : $newStatus],
            'note' => $note,
        ]);
    }
}
