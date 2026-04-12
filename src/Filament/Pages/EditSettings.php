<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use App\Models\User;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Actions\Action;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentView;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use MiPress\Core\Filament\Clusters\WebCluster;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Setting;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\GlobalSeoSettingsManager;
use MiPress\Core\Services\SettingsManager;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @property Schema $form
 */
class EditSettings extends Page
{
    protected string $view = 'mipress::filament.pages.edit-settings';

    protected static ?string $cluster = WebCluster::class;

    protected static ?string $slug = 'settings/{handle}';

    protected static ?string $navigationLabel = 'Nastavení';

    protected static ?int $navigationSort = 10;

    /** @var list<string> */
    private const HIDDEN_NAVIGATION_HANDLES = ['scripts', 'seo', 'site', 'sitemap', 'media_conversions'];

    /** @var array<string, list<string>> */
    private const HIDDEN_FIELD_HANDLES = [
        'seo' => ['robots'],
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public string $handle = '';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-gear';
    }

    public static function canAccess(): bool
    {
        return once(static function (): bool {
            $user = auth()->user();

            if (! $user instanceof User) {
                return false;
            }

            if (! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::Admin->value,
            ])) {
                return false;
            }

            try {
                return $user->hasPermissionTo('settings.manage');
            } catch (PermissionDoesNotExist) {
                return true;
            } catch (\Throwable) {
                return true;
            }
        });
    }

    public static function getNavigationItems(): array
    {
        if (! static::canAccess()) {
            return [];
        }

        return static::getNavigationSettings()
            ->map(fn (Setting $setting): NavigationItem => NavigationItem::make($setting->name)
                ->icon(static::resolveNavigationIcon($setting->icon))
                ->sort((int) $setting->sort_order)
                ->url(static::getUrl(['handle' => $setting->handle]))
                ->isActiveWhen(fn (): bool => request()->route('handle') === $setting->handle)
            )
            ->values()
            ->all();
    }

    public function mount(string $handle): void
    {
        $this->handle = $handle;

        if ($redirectUrl = $this->getDedicatedSettingsUrl($handle)) {
            $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));

            return;
        }

        $setting = $this->resolveSetting();

        $this->form->fill([
            'data' => $setting->data ?? [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        $setting = $this->resolveSetting()->loadMissing('blueprint');

        return $form
            ->schema(BlueprintFieldResolver::resolveAll(
                static::filterHiddenFields($setting->blueprint?->fields ?? [], $setting->handle),
            ))
            ->statePath('data');
    }

    public function save(): void
    {
        $setting = $this->resolveSetting();

        $state = $this->form->getState();

        $payload = $this->normalizeSettingsPayload(
            is_array($state['data'] ?? null)
                ? $state['data']
                : [],
        );

        $boundPayload = $this->normalizeSettingsPayload(
            is_array($this->data)
                ? $this->data
                : [],
        );

        if ($boundPayload !== []) {
            $payload = array_replace_recursive($payload, $boundPayload);
        }

        $setting->data = $payload;

        $setting->save();

        app(SettingsManager::class)->flush();

        Notification::make()
            ->title('Nastavení bylo uloženo')
            ->body('Sekce "'.$setting->name.'" byla úspěšně uložena.')
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSettingsPayload(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->normalizeSettingsPayload($value);
            }

            if (is_string($key) && str_contains($key, '.')) {
                data_set($normalized, $key, $value);

                continue;
            }

            $normalized[$key] = $value;
        }

        while (is_array($normalized['data'] ?? null)) {
            $normalized = $normalized['data'];
        }

        return $normalized;
    }

    /**
     * @return array<string, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Uložit nastavení')
                ->icon('fal-floppy-disk')
                ->action('save'),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->resolveSetting()->name;
    }

    private function resolveSetting(): Setting
    {
        if (in_array($this->handle, [GlobalSeoSettingsManager::HANDLE, 'site', 'sitemap'], true)) {
            throw new NotFoundHttpException();
        }

        $setting = app(SettingsManager::class)->find($this->handle);

        if ($setting === null) {
            throw new NotFoundHttpException();
        }

        return $setting;
    }

    private function getDedicatedSettingsUrl(string $handle): ?string
    {
        return match ($handle) {
            GlobalSeoSettingsManager::HANDLE => GlobalSeoSettings::getUrl(),
            'sitemap' => SitemapSettings::getUrl(),
            default => null,
        };
    }

    /**
     * @return Collection<int, Setting>
     */
    private static function getNavigationSettings(): Collection
    {
        $request = request();
        $cacheKey = 'mipress.settings.navigation';

        if ($request->attributes->has($cacheKey)) {
            /** @var Collection<int, Setting> $settings */
            $settings = $request->attributes->get($cacheKey);

            return $settings;
        }

        $settings = app(SettingsManager::class)
            ->all()
            ->reject(fn (Setting $setting): bool => in_array($setting->handle, self::HIDDEN_NAVIGATION_HANDLES, true))
            ->sortBy('sort_order')
            ->values();

        $request->attributes->set($cacheKey, $settings);

        return $settings;
    }

    private static function resolveNavigationIcon(?string $icon): string
    {
        static $resolvedIcons = [];

        $icon ??= 'fal-gear';
        $icon = static::normalizeNavigationIcon($icon);

        if (array_key_exists($icon, $resolvedIcons)) {
            return $resolvedIcons[$icon];
        }

        try {
            app(IconFactory::class)->svg($icon);

            return $resolvedIcons[$icon] = $icon;
        } catch (SvgNotFound) {
            return $resolvedIcons[$icon] = 'fal-gear';
        }
    }

    private static function normalizeNavigationIcon(string $icon): string
    {
        return match ($icon) {
            'fal-search' => 'fal-magnifying-glass',
            'far-search' => 'far-magnifying-glass',
            'fas-search' => 'fas-magnifying-glass',
            default => $icon,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function filterHiddenFields(array $fields, string $handle): array
    {
        $hiddenFields = self::HIDDEN_FIELD_HANDLES[$handle] ?? [];

        if ($hiddenFields === []) {
            return $fields;
        }

        return collect($fields)
            ->map(function (array $item) use ($hiddenFields): ?array {
                if (is_array($item['fields'] ?? null)) {
                    $item['fields'] = collect($item['fields'])
                        ->filter(fn (mixed $field): bool => is_array($field) && ! in_array($field['handle'] ?? null, $hiddenFields, true))
                        ->values()
                        ->all();

                    return $item['fields'] === [] ? null : $item;
                }

                return in_array($item['handle'] ?? null, $hiddenFields, true) ? null : $item;
            })
            ->filter()
            ->values()
            ->all();
    }
}
