<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MiPress\Core\Models\GlobalSet;

class GlobalSetManager
{
    public const CACHE_KEY = 'mipress.global_sets';

    private const CACHE_TTL = 3600;

    /** @var Collection<int, GlobalSet>|null */
    private ?Collection $sets = null;

    /**
     * @return Collection<int, GlobalSet>
     */
    public function all(): Collection
    {
        return $this->loadSets();
    }

    public function find(string $handle): ?GlobalSet
    {
        return $this->loadSets()->firstWhere('handle', $handle);
    }

    public function get(string $handle, string $key, mixed $default = null): mixed
    {
        return $this->find($handle)?->get($key, $default) ?? $default;
    }

    public function flush(): void
    {
        $this->sets = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return Collection<int, GlobalSet>
     */
    private function loadSets(): Collection
    {
        if ($this->sets === null) {
            try {
                $this->sets = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => GlobalSet::all());
            } catch (\Throwable) {
                $this->sets = collect();
            }
        }

        return $this->sets;
    }
}
