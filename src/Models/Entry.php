<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MiPress\Core\Database\Factories\EntryFactory;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Traits\Auditable;
use MiPress\Core\Traits\HasRevisions;
use MiPress\Core\Traits\HasSeo;
use MiPress\Core\Traits\HasWorkflow;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Entry extends Model
{
    use Auditable;
    use HasFactory;
    use HasRevisions;
    use HasSeo;
    use HasSlug;
    use HasWorkflow;
    use SoftDeletes;

    protected $table = 'entries';

    protected $fillable = [
        'collection_id',
        'blueprint_id',
        'title',
        'slug',
        'data',
        'status',
        'published_at',
        'scheduled_at',
        'meta_title',
        'meta_description',
        'og_image_id',
        'author_id',
        'sort_order',
        'parent_id',
        'origin_id',
        'locale',
        'review_note',
        'featured_image_id',
    ];

    protected array $auditExclude = ['data'];

    protected $attributes = [
        'data' => '{}',
        'status' => 'draft',
        'sort_order' => 0,
        'locale' => 'cs',
    ];

    protected $casts = [
        'collection_id' => 'integer',
        'blueprint_id' => 'integer',
        'data' => 'array',
        'status' => EntryStatus::class,
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'author_id' => 'integer',
        'featured_image_id' => 'integer',
        'og_image_id' => 'integer',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
        'origin_id' => 'integer',
    ];

    protected static function newFactory(): EntryFactory
    {
        return EntryFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            if (! $entry->collection_id) {
                $entry->blueprint_id = null;

                return;
            }

            $resolvedCollectionId = (int) $entry->collection_id;
            $collectionBlueprintId = null;

            if (
                $entry->relationLoaded('collection')
                && $entry->collection !== null
                && (int) $entry->collection->getKey() === $resolvedCollectionId
            ) {
                $collectionBlueprintId = $entry->collection->blueprint_id;
            } else {
                $collectionBlueprintId = Collection::query()
                    ->whereKey($resolvedCollectionId)
                    ->value('blueprint_id');
            }

            $entry->blueprint_id = is_numeric($collectionBlueprintId)
                ? (int) $collectionBlueprintId
                : null;
        });

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(CuratorMedia::class, 'featured_image_id');
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(CuratorMedia::class, 'og_image_id');
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'entry_term');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'origin_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('origin_id');
    }

    public function getPublicUrl(): ?string
    {
        $collection = $this->collection;

        if (! $collection instanceof Collection || ! filled($collection->route)) {
            return null;
        }

        $missingParameter = false;
        $resolved = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $matches) use (&$missingParameter): string {
                $value = $this->resolveRouteParameter($matches[1]);

                if (! filled($value)) {
                    $missingParameter = true;

                    return '';
                }

                return $value;
            },
            $collection->route,
        );

        if (! is_string($resolved) || $missingParameter) {
            return null;
        }

        $resolved = preg_replace('#/+#', '/', $resolved) ?? $resolved;

        return filled($resolved) ? $resolved : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMasonContent(): array
    {
        $content = $this->data['content'] ?? null;

        if (! is_array($content)) {
            return [];
        }

        return $content;
    }

    public function hasMasonContent(): bool
    {
        return $this->getMasonContent() !== [];
    }

    public function renderMasonContent(): string
    {
        return MasonRenderer::make($this->getMasonContent())
            ->bricks(EditorialBrickCollection::make())
            ->toUnsafeHtml();
    }

    public function getExcerpt(int $words = 28): string
    {
        foreach (['excerpt', 'summary', 'perex', 'intro'] as $key) {
            $value = $this->data[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $text = $this->extractReadableText();

        return (string) str($text)->squish()->words($words, '…');
    }

    public function getReadingTimeMinutes(): int
    {
        $configured = $this->data['reading_time'] ?? null;

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $wordCount = str_word_count($this->extractReadableText());

        return max(1, (int) ceil($wordCount / 220));
    }

    private function resolveRouteParameter(string $parameter): ?string
    {
        return match ($parameter) {
            'slug' => $this->slug,
            'year' => $this->published_at?->format('Y'),
            'month' => $this->published_at?->format('m'),
            'day' => $this->published_at?->format('d'),
            default => $this->resolveCustomRouteParameter($parameter),
        };
    }

    private function resolveCustomRouteParameter(string $parameter): ?string
    {
        $dataValue = $this->data[$parameter] ?? null;

        if (is_scalar($dataValue) && $dataValue !== '') {
            return (string) $dataValue;
        }

        $attributeValue = $this->getAttribute($parameter);

        if (is_scalar($attributeValue) && $attributeValue !== '') {
            return (string) $attributeValue;
        }

        return null;
    }

    private function extractReadableText(): string
    {
        if ($this->hasMasonContent()) {
            return MasonRenderer::make($this->getMasonContent())
                ->bricks(EditorialBrickCollection::make())
                ->toText();
        }

        $segments = [];

        foreach ($this->data as $key => $value) {
            if (in_array($key, ['meta_title', 'meta_description', 'reading_time'], true)) {
                continue;
            }

            if (is_string($value)) {
                $segments[] = strip_tags($value);
            }
        }

        return trim(implode(' ', $segments));
    }
}
