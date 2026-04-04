<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\GlobalSet;
use MiPress\Core\Models\Page as PageModel;
use MiPress\Core\Models\Setting;
use MiPress\Core\Services\GlobalSetManager;

/**
 * @property-read Schema $form
 */
class SiteSettings extends Page
{
    protected string $view = 'mipress::filament.pages.site-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'fal-gear';

    protected static ?string $navigationLabel = 'Nastavení webu';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Nastavení webu';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

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
        $general = $this->getGlobalSet('general');
        $site = $this->getGlobalSet('site');
        $social = $this->getGlobalSet('social');
        $seo = $this->getGlobalSet('seo');

        $this->form->fill([
            'site_name' => $general?->get('site_name', ''),
            'email' => $general?->get('email', ''),
            'phone' => $general?->get('phone', ''),
            'address' => $general?->get('address', ''),
            'homepage_page_id' => Setting::getValue('site.homepage_page_id'),
            'default_locale' => $site?->get('default_locale', 'cs'),
            'date_format' => $site?->get('date_format', 'j. F Y'),
            'per_page' => $site?->get('per_page', 12),
            'facebook' => $social?->get('facebook', ''),
            'instagram' => $social?->get('instagram', ''),
            'youtube' => $social?->get('youtube', ''),
            'linkedin' => $social?->get('linkedin', ''),
            'meta_title_suffix' => $seo?->get('meta_title_suffix', ''),
            'meta_description' => $seo?->get('meta_description', ''),
            'gtm_code' => $seo?->get('gtm_code', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make()
                        ->tabs([
                            Tabs\Tab::make('Obecné')
                                ->icon('fal-building')
                                ->schema([
                                    Section::make('Identita webu')->schema([
                                        TextInput::make('site_name')
                                            ->label('Název webu')
                                            ->maxLength(255)
                                            ->placeholder('Můj web'),
                                        Select::make('homepage_page_id')
                                            ->label('Úvodní stránka')
                                            ->options(fn (): array => PageModel::query()
                                                ->orderBy('title')
                                                ->pluck('title', 'id')
                                                ->toArray())
                                            ->searchable()
                                            ->placeholder('Žádná (výchozí)')
                                            ->helperText('Zvolte stránku, která se zobrazí jako homepage.'),
                                    ]),
                                    Section::make('Kontakt')->schema([
                                        TextInput::make('email')
                                            ->label('E-mail')
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label('Telefon')
                                            ->tel()
                                            ->maxLength(50),
                                        TextInput::make('address')
                                            ->label('Adresa')
                                            ->maxLength(500),
                                    ]),
                                ]),
                            Tabs\Tab::make('Web')
                                ->icon('fal-gear')
                                ->schema([
                                    Section::make('Regionální nastavení')->schema([
                                        Select::make('default_locale')
                                            ->label('Výchozí jazyk')
                                            ->options([
                                                'cs' => 'Čeština',
                                                'sk' => 'Slovenčina',
                                                'en' => 'English',
                                                'de' => 'Deutsch',
                                            ])
                                            ->required(),
                                        Select::make('date_format')
                                            ->label('Formát data')
                                            ->options([
                                                'j. F Y' => now()->translatedFormat('j. F Y'),
                                                'j. n. Y' => now()->format('j. n. Y'),
                                                'd.m.Y' => now()->format('d.m.Y'),
                                                'Y-m-d' => now()->format('Y-m-d'),
                                            ])
                                            ->required(),
                                    ]),
                                    Section::make('Zobrazení')->schema([
                                        TextInput::make('per_page')
                                            ->label('Položek na stránku')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(100)
                                            ->default(12),
                                    ]),
                                ]),
                            Tabs\Tab::make('Sociální sítě')
                                ->icon('fal-share-nodes')
                                ->schema([
                                    Section::make('Profily')->schema([
                                        TextInput::make('facebook')
                                            ->label('Facebook')
                                            ->url()
                                            ->maxLength(500)
                                            ->prefix('https://'),
                                        TextInput::make('instagram')
                                            ->label('Instagram')
                                            ->url()
                                            ->maxLength(500)
                                            ->prefix('https://'),
                                        TextInput::make('youtube')
                                            ->label('YouTube')
                                            ->url()
                                            ->maxLength(500)
                                            ->prefix('https://'),
                                        TextInput::make('linkedin')
                                            ->label('LinkedIn')
                                            ->url()
                                            ->maxLength(500)
                                            ->prefix('https://'),
                                    ]),
                                ]),
                            Tabs\Tab::make('SEO')
                                ->icon('fal-magnifying-glass')
                                ->schema([
                                    Section::make('Výchozí meta data')->schema([
                                        TextInput::make('meta_title_suffix')
                                            ->label('Přípona titulku')
                                            ->maxLength(100)
                                            ->placeholder('| Můj web')
                                            ->helperText('Připojí se za titulek každé stránky.'),
                                        TextInput::make('meta_description')
                                            ->label('Výchozí meta popis')
                                            ->maxLength(500)
                                            ->helperText('Použije se, pokud stránka nemá vlastní meta popis.'),
                                    ]),
                                    Section::make('Analytika')->schema([
                                        TextInput::make('gtm_code')
                                            ->label('Google Tag Manager ID')
                                            ->maxLength(50)
                                            ->placeholder('GTM-XXXXXXX'),
                                    ]),
                                ]),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Uložit nastavení')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->saveGeneralSet($data);
        $this->saveSiteSet($data);
        $this->saveSocialSet($data);
        $this->saveSeoSet($data);
        $this->saveHomepageSetting($data);

        app(GlobalSetManager::class)->flush();

        Notification::make()
            ->success()
            ->title('Nastavení uloženo')
            ->send();
    }

    private function saveGeneralSet(array $data): void
    {
        $this->updateGlobalSet('general', 'Obecné', [
            'site_name' => $data['site_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'address' => $data['address'] ?? '',
        ]);
    }

    private function saveSiteSet(array $data): void
    {
        $this->updateGlobalSet('site', 'Nastavení webu', [
            'default_locale' => $data['default_locale'] ?? 'cs',
            'date_format' => $data['date_format'] ?? 'j. F Y',
            'per_page' => (int) ($data['per_page'] ?? 12),
        ]);
    }

    private function saveSocialSet(array $data): void
    {
        $this->updateGlobalSet('social', 'Sociální sítě', [
            'facebook' => $data['facebook'] ?? '',
            'instagram' => $data['instagram'] ?? '',
            'youtube' => $data['youtube'] ?? '',
            'linkedin' => $data['linkedin'] ?? '',
        ]);
    }

    private function saveSeoSet(array $data): void
    {
        $this->updateGlobalSet('seo', 'SEO', [
            'meta_title_suffix' => $data['meta_title_suffix'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'gtm_code' => $data['gtm_code'] ?? '',
        ]);
    }

    private function saveHomepageSetting(array $data): void
    {
        Setting::putValue('site.homepage_page_id', $data['homepage_page_id'] ?? null);
    }

    private function getGlobalSet(string $handle): ?GlobalSet
    {
        return app(GlobalSetManager::class)->find($handle);
    }

    private function updateGlobalSet(string $handle, string $title, array $data): void
    {
        GlobalSet::updateOrCreate(
            ['handle' => $handle],
            ['title' => $title, 'data' => $data],
        );
    }
}
