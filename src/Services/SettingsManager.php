<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Closure;
use Illuminate\Support\Collection;
use MiPress\Core\Models\Setting;

class SettingsManager
{
    /** @var Collection<int, Setting>|null */
    private ?Collection $settings = null;

    private ?Closure $staticCacheInvalidator = null;

    /**
     * @return Collection<int, Setting>
     */
    public function all(): Collection
    {
        if ($this->settings === null) {
            $this->settings = Setting::query()
                ->with('blueprint')
                ->orderBy('sort_order')
                ->get();
        }

        return $this->settings;
    }

    public function find(string $handle): ?Setting
    {
        return $this->all()->firstWhere('handle', $handle);
    }

    public function get(string $handle, ?string $key = null, mixed $default = null): mixed
    {
        $setting = $this->find($handle);

        if (! $setting) {
            return $default;
        }

        if ($key === null) {
            return $setting->data ?? [];
        }

        return $setting->get($key, $default);
    }

    public function flush(): void
    {
        $this->settings = null;

        if ($this->staticCacheInvalidator !== null) {
            ($this->staticCacheInvalidator)();
        }
    }

    public function registerStaticCacheInvalidator(Closure $callback): void
    {
        $this->staticCacheInvalidator = $callback;
    }
}
