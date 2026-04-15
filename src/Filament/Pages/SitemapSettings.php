<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Clusters\SeoCluster;
use MiPress\Core\Jobs\GenerateSitemapJob;
use MiPress\Core\Models\Setting;

/**
 * @property Schema $form
 */
class SitemapSettings extends Page
{
    protected string $view = 'mipress::filament.pages.sitemap-settings';

    protected static ?string $cluster = SeoCluster::class;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 30;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-sitemap';
    }

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.pages.sitemap.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mipress::admin.pages.sitemap.title');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }

    public function mount(): void
    {
        $this->form->fill([
            'enabled' => Setting::getValue('sitemap.enabled', '1') === '1',
            'auto_generate' => Setting::getValue('sitemap.auto_generate', '1') === '1',
            'static_urls' => json_decode(Setting::getValue('sitemap.static_urls', '[]') ?? '[]', true) ?: [],
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('mipress::admin.pages.sitemap.sections.general'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('mipress::admin.pages.sitemap.fields.enabled'))
                            ->helperText(__('mipress::admin.pages.sitemap.fields.enabled_helper'))
                            ->default(true),
                        Toggle::make('auto_generate')
                            ->label(__('mipress::admin.pages.sitemap.fields.auto_generate'))
                            ->helperText(__('mipress::admin.pages.sitemap.fields.auto_generate_helper'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.sitemap.sections.static_urls'))
                    ->description(__('mipress::admin.pages.sitemap.sections.static_urls_description'))
                    ->schema([
                        Repeater::make('static_urls')
                            ->label('')
                            ->schema([
                                TextInput::make('url')
                                    ->label(__('mipress::admin.pages.sitemap.fields.url'))
                                    ->placeholder('/')
                                    ->required()
                                    ->maxLength(500),
                                Select::make('changefreq')
                                    ->label(__('mipress::admin.pages.sitemap.fields.changefreq'))
                                    ->options([
                                        'always' => __('mipress::admin.pages.sitemap.changefreq.always'),
                                        'hourly' => __('mipress::admin.pages.sitemap.changefreq.hourly'),
                                        'daily' => __('mipress::admin.pages.sitemap.changefreq.daily'),
                                        'weekly' => __('mipress::admin.pages.sitemap.changefreq.weekly'),
                                        'monthly' => __('mipress::admin.pages.sitemap.changefreq.monthly'),
                                        'yearly' => __('mipress::admin.pages.sitemap.changefreq.yearly'),
                                        'never' => __('mipress::admin.pages.sitemap.changefreq.never'),
                                    ])
                                    ->default('weekly'),
                                Select::make('priority')
                                    ->label(__('mipress::admin.pages.sitemap.fields.priority'))
                                    ->options([
                                        '1.0' => __('mipress::admin.pages.sitemap.priority.1_0'),
                                        '0.8' => __('mipress::admin.pages.sitemap.priority.0_8'),
                                        '0.6' => __('mipress::admin.pages.sitemap.priority.0_6'),
                                        '0.5' => __('mipress::admin.pages.sitemap.priority.0_5'),
                                        '0.3' => __('mipress::admin.pages.sitemap.priority.0_3'),
                                        '0.1' => __('mipress::admin.pages.sitemap.priority.0_1'),
                                    ])
                                    ->default('0.5'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['url'] ?? null),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<string, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('mipress::admin.pages.sitemap.actions.save'))
                ->action('save')
                ->icon('fal-floppy-disk'),

            Action::make('generate')
                ->label(__('mipress::admin.pages.sitemap.actions.generate'))
                ->action('generateSitemap')
                ->icon('fal-rotate')
                ->color('gray'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::putValue('sitemap.enabled', ($state['enabled'] ?? true) ? '1' : '0');
        Setting::putValue('sitemap.auto_generate', ($state['auto_generate'] ?? true) ? '1' : '0');
        Setting::putValue('sitemap.static_urls', json_encode($state['static_urls'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        Notification::make()
            ->title(__('mipress::admin.pages.sitemap.saved_title'))
            ->body(__('mipress::admin.pages.sitemap.saved_body'))
            ->success()
            ->send();
    }

    public function generateSitemap(): void
    {
        GenerateSitemapJob::dispatch();

        Notification::make()
            ->title(__('mipress::admin.pages.sitemap.generated_title'))
            ->body(__('mipress::admin.pages.sitemap.generated_body'))
            ->success()
            ->send();
    }

    public function getLastGeneratedInfo(): ?string
    {
        $at = Setting::getValue('sitemap.last_generated_at');

        if ($at === null) {
            return null;
        }

        $count = Setting::getValue('sitemap.last_url_count', '0');

        return __('mipress::admin.pages.sitemap.last_generated', ['at' => $at, 'count' => $count]);
    }
}
