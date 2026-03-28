<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MiPress\Core\Database\Factories\CollectionFactory;

class Collection extends Model
{
    use HasFactory;

    protected $table = 'collections';

    protected $fillable = [
        'name',
        'handle',
        'blueprint_id',
        'icon',
        'route',
        'dated',
        'slugs',
        'sort_direction',
        'sort_order',
    ];

    protected $casts = [
        'dated' => 'boolean',
        'slugs' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function newFactory(): CollectionFactory
    {
        return CollectionFactory::new();
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
