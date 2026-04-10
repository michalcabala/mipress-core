<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use MiPress\Core\Models\Setting;

class SettingsManager
{
    private const SETTINGS_TABLE_EXISTS_CACHE_KEY = 'mipress.settings.table_exists';

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
        if (! $this->settingsTableExists()) {
            return collect();
        }

        try {
            return Setting::query()
                ->orderBy('sort_order')
                ->get();
        } catch (QueryException $exception) {
            Log::warning('Unable to load miPress settings.', [
                'message' => $exception->getMessage(),
            ]);

            return collect();
        }
    }

    private function settingsTableExists(): bool
    {
        if (Cache::memo()->get(self::SETTINGS_TABLE_EXISTS_CACHE_KEY) === true) {
            return true;
        }

        if (! Schema::hasTable('settings')) {
            return false;
        }

        Cache::memo()->forever(self::SETTINGS_TABLE_EXISTS_CACHE_KEY, true);

        return true;
    }
}
