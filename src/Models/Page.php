<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Awcodes\Mason\Support\MasonRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MiPress\Core\Database\Factories\PageFactory;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Traits\HasRevisions;
use MiPress\Core\Traits\HasSeo;
use MiPress\Core\Traits\HasWorkflow;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Page extends Model
{
    use HasFactory;
    use HasRevisions;
    use HasSeo;
    use HasSlug;
    use HasWorkflow;
    use SoftDeletes;

    protected $table = 'pages';

    protected $fillable = [
        'blueprint_id',
        'title',
        'slug',
        'content',
        'data',
        'status',
        'published_at',
        'scheduled_at',
        'meta_title',
        'meta_description',
        'author_id',
        'sort_order',
        'parent_id',
        'origin_id',
        'featured_image_id',
        'locale',
        'review_note',
    ];

    protected $attributes = [
        'data' => '{}',
        'status' => 'draft',
        'sort_order' => 0,
        'locale' => 'cs',
    ];

    protected $casts = [
        'blueprint_id' => 'integer',
        'content' => 'array',
        'data' => 'array',
        'status' => ContentStatus::class,
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'author_id' => 'integer',
        'sort_order' => 'integer',
        'parent_id' => 'integer',
        'featured_image_id' => 'integer',
        'origin_id' => 'integer',
    ];

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(200);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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

    /** @future multi-lang — translation origin link (currently unused, locale infra prepared) */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(self::class, 'origin_id');
    }

    /** @future multi-lang — all translations of this page (currently unused) */
    public function translations(): HasMany
    {
        return $this->hasMany(self::class, 'origin_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /** @future multi-lang — filter by locale (currently unused) */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /** @future multi-lang — only original-language records (currently unused) */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('origin_id');
    }

    public function getPublicUrl(): ?string
    {
        if (! filled($this->slug)) {
            return null;
        }

        return '/'.$this->slug;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMasonContent(): array
    {
        // Prefer new `content` column, fall back to legacy `data['content']`
        $content = $this->content ?? ($this->data['content'] ?? null);

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
