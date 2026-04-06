<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Actions\Action;
use App\Models\User;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Setting;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\SettingsManager;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditSettings extends Page
{
    protected string $view = 'mipress::filament.pages.edit-settings';

    protected static ?string $slug = 'settings/{handle}';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $navigationLabel = 'Nastavení';

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed> */
    public array $data = [];

    public string $handle = '';

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-gear';
    }

    public static function canAccess(): bool
    {
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
            // During rollout, permission may be missing until seeders run.
            if (! SchemaFacade::hasTable('permissions')) {
                return true;
            }

            $permissionExists = Permission::query()
                ->where('name', 'settings.manage')
                ->where('guard_name', 'web')
                ->exists();

            if (! $permissionExists) {
                return true;
            }

            return $user->hasPermissionTo('settings.manage');
        } catch (\Throwable) {
            return true;
        }
    }

    public static function getNavigationItems(): array
    {
        if (! static::canAccess()) {
            return [];
        }

        return static::getNavigationSettings()
            ->map(fn (\MiPress\Core\Models\Setting $setting): NavigationItem => NavigationItem::make($setting->name)
                ->group('Nastavení')
                ->icon($setting->icon ?: 'fal-gear')
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
        $setting = $this->resolveSetting();

        $this->form->fill([
            'data' => $setting->data ?? [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        $setting = $this->resolveSetting();

        return $form->schema(
            BlueprintFieldResolver::resolveAll($setting->blueprint?->fields ?? []),
        );
    }

    public function save(): void
    {
        $setting = $this->resolveSetting();

        $state = $this->form->getState();

        $payload = is_array($state['data'] ?? null)
            ? $state['data']
            : [];

        if ($payload === [] && is_array($this->data)) {
            $payload = $this->data;
        }

        // Blueprint sections currently use statePath('data'), which may nest once more on Page forms.
        if (is_array($payload['data'] ?? null)) {
            $payload = $payload['data'];
        }

        $setting->data = $payload;

        $setting->save();

        app(SettingsManager::class)->flush();

        Notification::make()
            ->title('Nastavení bylo uloženo')
            ->success()
            ->send();
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
        $setting = app(SettingsManager::class)->find($this->handle);

        if ($setting === null) {
            throw new NotFoundHttpException();
        }

        return $setting;
    }

    /**
     * @return Collection<int, \MiPress\Core\Models\Setting>
     */
    private static function getNavigationSettings(): Collection
    {
        $request = request();
        $cacheKey = 'mipress.settings.navigation';

        if ($request->attributes->has($cacheKey)) {
            /** @var Collection<int, \MiPress\Core\Models\Setting> $settings */
            $settings = $request->attributes->get($cacheKey);

            return $settings;
        }

        $settings = app(SettingsManager::class)
            ->all()
            ->sortBy('sort_order')
            ->values();

        $request->attributes->set($cacheKey, $settings);

        return $settings;
    }
}
