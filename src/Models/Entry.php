<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MiPress\Core\Database\Factories\EntryFactory;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Traits\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Entry extends Model implements HasMedia
{
    use Auditable, HasFactory, HasSlug, InteractsWithMedia, SoftDeletes;

    protected $table = 'entries';

    protected $fillable = [
        'collection_id',
        'blueprint_id',
        'title',
        'slug',
        'data',
        'status',
        'published_at',
        'author_id',
        'sort_order',
        'origin_id',
        'locale',
        'review_note',
    ];

    protected array $auditExclude = ['data'];

    protected $attributes = [
        'data' => '{}',
        'status' => 'draft',
        'sort_order' => 0,
        'locale' => 'cs',
    ];

    protected $casts = [
        'data' => 'array',
        'status' => EntryStatus::class,
        'published_at' => 'datetime',
        'dated' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(200);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured_image')->singleFile();
        $this->addMediaCollection('gallery');
        $this->addMediaCollection('attachments');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Published)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', EntryStatus::Draft);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
