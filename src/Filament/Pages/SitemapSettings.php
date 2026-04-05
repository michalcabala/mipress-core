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

    protected static ?string $navigationLabel = 'Správa sitemapy';

    protected static ?string $title = 'Nastavení sitemapy';

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-sitemap';
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
                Section::make('Obecné')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Sitemap aktivní')
                            ->helperText('Povolí generování souboru sitemap.xml.')
                            ->default(true),
                        Toggle::make('auto_generate')
                            ->label('Generovat při publikaci')
                            ->helperText('Automaticky přegeneruje sitemapu při publikaci nebo zrušení publikace obsahu.')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Statické URL')
                    ->description('URL adresy, které nejsou generovány z obsahu (např. homepage, kontakt).')
                    ->schema([
                        Repeater::make('static_urls')
                            ->label('')
                            ->schema([
                                TextInput::make('url')
                                    ->label('URL cesta')
                                    ->placeholder('/')
                                    ->required()
                                    ->maxLength(500),
                                Select::make('changefreq')
                                    ->label('Frekvence změn')
                                    ->options([
                                        'always' => 'Vždy',
                                        'hourly' => 'Každou hodinu',
                                        'daily' => 'Denně',
                                        'weekly' => 'Týdně',
                                        'monthly' => 'Měsíčně',
                                        'yearly' => 'Ročně',
                                        'never' => 'Nikdy',
                                    ])
                                    ->default('weekly'),
                                Select::make('priority')
                                    ->label('Priorita')
                                    ->options([
                                        '1.0' => '1.0 — Nejvyšší',
                                        '0.8' => '0.8 — Vysoká',
                                        '0.6' => '0.6 — Střední',
                                        '0.5' => '0.5 — Výchozí',
                                        '0.3' => '0.3 — Nízká',
                                        '0.1' => '0.1 — Nejnižší',
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
                ->label('Uložit nastavení')
                ->action('save')
                ->icon('fal-floppy-disk'),

            Action::make('generate')
                ->label('Generovat sitemapu')
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
            ->title('Nastavení sitemapy uloženo')
            ->success()
            ->send();
    }

    public function generateSitemap(): void
    {
        GenerateSitemapJob::dispatch();

        Notification::make()
            ->title('Generování sitemapy spuštěno')
            ->body('Sitemap bude přegenerována na pozadí.')
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

        return "Poslední generování: {$at} ({$count} URL)";
    }
}
