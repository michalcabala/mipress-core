<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Taxonomy extends Model
{
    use HasFactory, HasSlug, SoftDeletes;

    protected $table = 'taxonomies';

    protected $fillable = [
        'title',
        'handle',
        'is_hierarchical',
        'blueprint_id',
        'description',
        'collection_id',
        'show_in_entries_table',
        'show_in_entries_filter',
        'searchable_in_entries_table',
        'sortable_in_entries_table',
        'entries_table_display_mode',
        'entries_table_badge_palette',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'collection_id' => 'integer',
        'is_hierarchical' => 'boolean',
        'show_in_entries_table' => 'boolean',
        'show_in_entries_filter' => 'boolean',
        'searchable_in_entries_table' => 'boolean',
        'sortable_in_entries_table' => 'boolean',
        'entries_table_display_mode' => 'string',
        'entries_table_badge_palette' => 'string',
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
