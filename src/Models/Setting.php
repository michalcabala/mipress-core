<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use MiPress\Core\Database\Factories\SettingFactory;
use MiPress\Core\Services\SettingsManager;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'handle',
        'name',
        'blueprint_id',
        'data',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'blueprint_id' => 'int',
        'data' => 'array',
        'sort_order' => 'int',
    ];

    protected $attributes = [
        'data' => '{}',
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (Setting $setting): void {
            if (! preg_match('/^[a-z0-9_]+$/', $setting->handle)) {
                throw ValidationException::withMessages([
                    'handle' => 'Handle musí obsahovat pouze malá písmena, čísla a podtržítko.',
                ]);
            }
        });

        static::updating(function (Setting $setting): void {
            if ($setting->isDirty('handle')) {
                throw ValidationException::withMessages([
                    'handle' => 'Handle nelze po vytvoření měnit.',
                ]);
            }
        });

        static::saved(function (): void {
            if (app()->bound(SettingsManager::class)) {
                app(SettingsManager::class)->flush();
            }
        });

        static::deleted(function (): void {
            if (app()->bound(SettingsManager::class)) {
                app(SettingsManager::class)->flush();
            }
        });
    }

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data ?? [], $key, $default);
    }

    public function set(string $key, mixed $value): static
    {
        $data = $this->data ?? [];
        data_set($data, $key, $value);
        $this->data = $data;

        return $this;
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        [$handle, $path] = str_contains($key, '.')
            ? explode('.', $key, 2)
            : ['system', $key];

        $setting = static::query()->where('handle', $handle)->first();

        if (! $setting) {
            return $default;
        }

        $value = data_get($setting->data ?? [], $path, $default);

        return $value === null ? null : (string) $value;
    }

    public static function putValue(string $key, ?string $value): void
    {
        [$handle, $path] = str_contains($key, '.')
            ? explode('.', $key, 2)
            : ['system', $key];

        $setting = static::query()->firstOrCreate(
            ['handle' => $handle],
            [
                'name' => str($handle)->replace('_', ' ')->headline()->toString(),
                'icon' => 'fal-gear',
                'data' => [],
            ],
        );

        $data = $setting->data ?? [];

        if ($value === null) {
            data_forget($data, $path);

            $setting->data = $data;
            $setting->save();

            return;
        }

        data_set($data, $path, $value);

        $setting->data = $data;
        $setting->save();
    }
}
