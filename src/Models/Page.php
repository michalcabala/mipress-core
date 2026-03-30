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
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Traits\Auditable;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Page extends Model
{
    use Auditable, HasFactory, HasSlug, SoftDeletes;

    protected $table = 'pages';

    protected $fillable = [
        'blueprint_id',
        'title',
        'slug',
        'data',
        'status',
        'published_at',
        'author_id',
        'sort_order',
        'parent_id',
        'featured_image_id',
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
        'sort_order' => 'integer',
        'parent_id' => 'integer',
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
        return $this->belongsTo(\Awcodes\Curator\Models\Media::class, 'featured_image_id');
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

    public function getPublicUrl(): ?string
    {
        if (! filled($this->slug)) {
            return null;
        }

        return '/' . $this->slug;
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
