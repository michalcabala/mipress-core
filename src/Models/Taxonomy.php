<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Taxonomy extends Model
{
    use HasFactory, HasSlug;

    protected $table = 'taxonomies';

    protected $fillable = [
        'title',
        'handle',
        'is_hierarchical',
        'blueprint_id',
        'description',
        'collection_id',
    ];

    protected $casts = [
        'is_hierarchical' => 'boolean',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('handle')
            ->slugsShouldBeNoLongerThan(255);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
