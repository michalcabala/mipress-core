<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Support\Collection;
use MiPress\Core\Models\GlobalSet;

class GlobalSetManager
{
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

    /**
     * @return Collection<int, GlobalSet>
     */
    private function loadSets(): Collection
    {
        if ($this->sets === null) {
            try {
                $this->sets = GlobalSet::all();
            } catch (\Throwable) {
                $this->sets = collect();
            }
        }

        return $this->sets;
    }
}
