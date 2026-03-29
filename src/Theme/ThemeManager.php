<?php

declare(strict_types=1);

namespace MiPress\Core\Theme;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use MiPress\Core\Models\Setting;

class ThemeManager
{
    private const string CACHE_KEY = 'mipress.theme.active';

    private const string SETTING_KEY = 'theme.active';

    public const string DEFAULT_THEME = 'default';

    public function __construct(private readonly string $themesPath) {}

    /**
     * @return Collection<int, ThemeManifest>
     */
    public function discover(): Collection
    {
        if (! is_dir($this->themesPath)) {
            return collect();
        }

        return collect(glob($this->themesPath.'/*/theme.json') ?: [])
            ->map(function (string $manifestPath): ?ThemeManifest {
                try {
                    $data = json_decode(
                        (string) file_get_contents($manifestPath),
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    );

                    // Slug must match directory name exactly
                    $dirName = basename(dirname($manifestPath));
                    if (($data['slug'] ?? '') !== $dirName) {
                        return null;
                    }

                    return ThemeManifest::fromArray($data, dirname($manifestPath));
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values();
    }

    public function getActive(): string
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): string {
            try {
                return Setting::getValue(self::SETTING_KEY, self::DEFAULT_THEME) ?? self::DEFAULT_THEME;
            } catch (\Exception) {
                // settings table may not exist yet during initial install
                return self::DEFAULT_THEME;
            }
        });
    }

    public function activate(string $slug): void
    {
        if (! $this->exists($slug)) {
            throw new InvalidArgumentException("Theme '{$slug}' does not exist.");
        }

        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => $slug],
        );

        Cache::forget(self::CACHE_KEY);
        $this->registerViews();
    }

    public function exists(string $slug): bool
    {
        return is_dir($this->themesPath.'/'.$slug)
            && file_exists($this->themesPath.'/'.$slug.'/theme.json');
    }

    public function registerViews(): void
    {
        $finder = View::getFinder();
        $finder->flush();

        $active = $this->getActive();
        $defaultPath = $this->themesPath.'/'.self::DEFAULT_THEME.'/views';
        $activePath = $this->themesPath.'/'.$active.'/views';

        // Add default theme as fallback (appended — checked last)
        if (is_dir($defaultPath)) {
            $finder->addLocation($defaultPath);
        }

        // Prepend active theme path so it takes priority over default
        if ($active !== self::DEFAULT_THEME && is_dir($activePath)) {
            $finder->prependLocation($activePath);
        }
    }

    public function getThemesPath(): string
    {
        return $this->themesPath;
    }
}
