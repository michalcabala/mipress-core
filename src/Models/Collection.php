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

    public function getArchivePath(): ?string
    {
        if (! filled($this->route)) {
            return null;
        }

        $path = preg_replace('/\{[^}]+\}/', '', $this->route);

        if (! is_string($path)) {
            return null;
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = rtrim($path, '/');

        return $path === '' ? null : $path;
    }

    /**
     * @return array<string, string>|null
     */
    public function extractRouteParameters(string $path): ?array
    {
        if (! filled($this->route)) {
            return null;
        }

        $pattern = preg_replace_callback(
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/',
            static fn (array $matches): string => '(?P<'.$matches[1].'>[^/]+)',
            preg_quote($this->route, '#'),
        );

        if (! is_string($pattern)) {
            return null;
        }

        if (! preg_match('#^'.$pattern.'$#', $path, $matches)) {
            return null;
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    public function resolveSlugFromPath(string $path): ?string
    {
        $parameters = $this->extractRouteParameters($path);

        if ($parameters === null) {
            return null;
        }

        if (filled($parameters['slug'] ?? null)) {
            return $parameters['slug'];
        }

        $lastParameter = end($parameters);

        return is_string($lastParameter) && $lastParameter !== '' ? $lastParameter : null;
    }

    public function isArchivePath(string $path): bool
    {
        $normalizedPath = rtrim($path, '/');
        $normalizedPath = $normalizedPath === '' ? '/' : $normalizedPath;

        return $this->getArchivePath() === $normalizedPath;
    }

    public function applyPublicOrdering(Builder|HasMany $query): Builder|HasMany
    {
        if ($this->dated) {
            return $query->orderBy('published_at', $this->sort_direction ?: 'desc');
        }

        return $query
            ->orderBy('sort_order', $this->sort_direction ?: 'asc')
            ->orderBy('published_at', 'desc');
    }
}
