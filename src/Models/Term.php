<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Openplain\FilamentTreeView\Concerns\HasTreeStructure;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Term extends Model
{
    use HasFactory, HasSlug, HasTreeStructure;

    protected $table = 'terms';

    protected $fillable = [
        'taxonomy_id',
        'title',
        'slug',
        'data',
        'parent_id',
        'sort_order',
        'origin_id',
        'locale',
    ];

    protected $attributes = [
        'data' => '{}',
        'sort_order' => 0,
        'locale' => 'cs',
    ];

    protected $casts = [
        'data' => 'array',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(200)
            ->allowDuplicateSlugs();
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'origin_id');
    }

    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(Entry::class, 'entry_term');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('origin_id');
    }

    public function getOrderKeyName(): string
    {
        return 'sort_order';
    }
}
