<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            $this->settings = $this->loadSettings();
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

    /**
     * @return Collection<int, Setting>
     */
    private function loadSettings(): Collection
    {
        if (! Schema::hasTable('settings')) {
            return collect();
        }

        if (! Schema::hasColumn('settings', 'handle')) {
            return collect();
        }

        try {
            $query = Setting::query();

            if (Schema::hasColumn('settings', 'blueprint_id')) {
                $query->with('blueprint');
            }

            if (Schema::hasColumn('settings', 'sort_order')) {
                $query->orderBy('sort_order');
            } else {
                $query->orderBy('handle');
            }

            return $query->get();
        } catch (QueryException $exception) {
            Log::warning('Unable to load miPress settings.', [
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }
    }
}
