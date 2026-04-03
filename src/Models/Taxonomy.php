<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    use HasFactory;

    protected $table = 'taxonomies';

    protected $fillable = [
        'title',
        'handle',
        'is_hierarchical',
        'blueprint_id',
        'description',
    ];

    protected $casts = [
        'is_hierarchical' => 'boolean',
    ];

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_taxonomy');
    }
}
