<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MiPress\Core\Database\Factories\RevisionFactory;

class Revision extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'revisions';

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'user_id',
        'data',
        'note',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function newFactory(): RevisionFactory
    {
        return RevisionFactory::new();
    }

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
