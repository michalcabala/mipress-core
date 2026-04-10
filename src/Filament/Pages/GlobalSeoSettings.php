<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MiPress\Core\Filament\Clusters\SeoCluster;
use MiPress\Core\Services\GlobalSeoSettingsManager;
use MiPress\Core\Services\SeoResolver;

class GlobalSeoSettings extends Page
{
    protected string $view = 'mipress::filament.pages.global-seo-settings';

    protected static ?string $cluster = SeoCluster::class;

    protected static ?string $slug = 'seo';

    protected static ?string $navigationLabel = 'Globální SEO';

    protected static ?string $title = 'Globální SEO';

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-magnifying-glass';
    }

    public static function canAccess(): bool
    {
        return EditSettings::canAccess();
    }

    public function mount(): void
    {
        $this->data = $this->seoSettings()->all();
        $this->form->fill($this->data);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Výchozí metadata')
                    ->description('Výchozí title a description fallbacky pro stránky, které nemají vlastní SEO metadata.')
                    ->schema([
                        TextInput::make('metadata.default_title')
                            ->label('Výchozí název webu')
                            ->placeholder('MiPress Studio')
                            ->live(onBlur: true),
                        TextInput::make('metadata.homepage_title')
                            ->label('Homepage title')
                            ->placeholder('MiPress Studio | Tvorba webů')
                            ->helperText('Použije se jen na domovské stránce.')
                            ->live(onBlur: true),
                        TextInput::make('metadata.title_suffix')
                            ->label('Suffix titulku')
                            ->placeholder(' | MiPress Studio')
                            ->helperText('Připojuje se k běžným stránkám a položkám bez ručně napsaného suffixu.')
                            ->live(onBlur: true),
                        Textarea::make('metadata.default_description')
                            ->label('Výchozí meta popis')
                            ->rows(4)
                            ->maxLength(180)
                            ->helperText('Použije se, když stránka ani obsah nemají vlastní popis ani použitelný excerpt.')
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Canonical a lokalizace')
                    ->description('Nastavení preferované domény, práce s query parametry a locale značkami.')
                    ->schema([
                        TextInput::make('canonical.base_url')
                            ->label('Preferovaná canonical base URL')
                            ->placeholder('https://www.example.cz')
                            ->url()
                            ->live(onBlur: true),
                        Select::make('canonical.trailing_slash')
                            ->label('Trailing slash')
                            ->options([
                                'keep' => 'Ponechat podle URL',
                                'add' => 'Vždy přidat',
                                'remove' => 'Vždy odstranit',
                            ])
                            ->default('keep'),
                        Toggle::make('canonical.strip_query_parameters')
                            ->label('Odebírat query parametry z canonical URL')
                            ->default(true),
                        Toggle::make('canonical.force_https')
                            ->label('Vynucovat HTTPS v canonical URL')
                            ->default(false),
                        TextInput::make('locale.html_lang')
                            ->label('HTML lang')
                            ->placeholder('cs')
                            ->helperText('Např. cs, cs-CZ nebo en.')
                            ->live(onBlur: true),
                        TextInput::make('locale.og_locale')
                            ->label('Open Graph locale')
                            ->placeholder('cs_CZ')
                            ->helperText('Např. cs_CZ nebo en_US.')
                            ->live(onBlur: true),
                        TagsInput::make('locale.alternate_og_locales')
                            ->label('Alternativní OG locale')
                            ->placeholder('en_US')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Open Graph a X')
                    ->description('Výchozí sociální metadata pro sdílení stránky.')
                    ->schema([
                        TextInput::make('open_graph.site_name')
                            ->label('OG site name')
                            ->placeholder('MiPress Studio')
                            ->helperText('Když necháte prázdné, použije se název webu z obecných nastavení.')
                            ->live(onBlur: true),
                        Select::make('twitter.card')
                            ->label('Twitter card')
                            ->options([
                                'summary_large_image' => 'summary_large_image',
                                'summary' => 'summary',
                            ])
                            ->default('summary_large_image'),
                        CuratorPicker::make('open_graph.default_image_id')
                            ->label('Výchozí OG obrázek')
                            ->helperText('Použije se tam, kde položka nebo stránka nemá vlastní OG nebo featured image.')
                            ->columnSpanFull(),
                        TextInput::make('open_graph.default_image_alt')
                            ->label('Alt výchozího OG obrázku')
                            ->placeholder('MiPress Studio')
                            ->columnSpanFull()
                            ->live(onBlur: true),
                        TextInput::make('twitter.site')
                            ->label('X účet webu')
                            ->placeholder('@mipress')
                            ->helperText('Může být s @ i bez něj.')
                            ->live(onBlur: true),
                        TextInput::make('twitter.creator')
                            ->label('Výchozí autor pro X')
                            ->placeholder('@michal')
                            ->live(onBlur: true),
                    ])
                    ->columns(2),

                Section::make('Structured data')
                    ->description('JSON-LD pro WebSite, Organization nebo LocalBusiness a základní WebPage nebo Article výstupy.')
                    ->schema([
                        Toggle::make('structured_data.enabled')
                            ->label('Zapnout structured data')
                            ->default(true),
                        Select::make('structured_data.organization_type')
                            ->label('Typ organizace')
                            ->options([
                                'Organization' => 'Organization',
                                'LocalBusiness' => 'LocalBusiness',
                            ])
                            ->default('Organization'),
                        TextInput::make('structured_data.organization_name')
                            ->label('Název organizace')
                            ->placeholder('MiPress Studio s.r.o.')
                            ->live(onBlur: true),
                        TextInput::make('structured_data.organization_url')
                            ->label('URL organizace')
                            ->placeholder('https://www.example.cz')
                            ->url()
                            ->live(onBlur: true),
                        CuratorPicker::make('structured_data.logo_id')
                            ->label('Logo pro JSON-LD')
                            ->columnSpanFull(),
                        TextInput::make('structured_data.phone')
                            ->label('Telefon'),
                        TextInput::make('structured_data.email')
                            ->label('E-mail')
                            ->email(),
                        TextInput::make('structured_data.street_address')
                            ->label('Ulice a číslo'),
                        TextInput::make('structured_data.address_locality')
                            ->label('Město'),
                        TextInput::make('structured_data.postal_code')
                            ->label('PSČ'),
                        TextInput::make('structured_data.address_country')
                            ->label('Kód země')
                            ->placeholder('CZ'),
                        TagsInput::make('structured_data.same_as')
                            ->label('SameAs URL')
                            ->placeholder('https://www.facebook.com/mipress')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Ověření a analytika')
                    ->description('Meta tagy pro ověření webu a volitelné vložení GA4 nebo GTM skriptů.')
                    ->schema([
                        TextInput::make('verification.google')
                            ->label('Google site verification'),
                        TextInput::make('verification.bing')
                            ->label('Bing Webmaster Tools'),
                        TextInput::make('verification.seznam')
                            ->label('Seznam Webmaster'),
                        TextInput::make('verification.facebook_domain')
                            ->label('Facebook domain verification'),
                        TextInput::make('analytics.google_analytics_id')
                            ->label('GA4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX'),
                        TextInput::make('analytics.google_tag_manager_id')
                            ->label('Google Tag Manager ID')
                            ->placeholder('GTM-XXXXXXX'),
                    ])
                    ->columns(2),

                Section::make('Náhled a kontrola')
                    ->description('Živý přehled nad výsledným title, description, social meta a základními riziky.')
                    ->schema([
                        Placeholder::make('health')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => $this->renderWarnings())
                            ->columnSpanFull(),
                        Placeholder::make('serp_preview')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => $this->renderSerpPreview()),
                        Placeholder::make('social_preview')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => $this->renderSocialPreview()),
                    ])
                    ->columns(2),
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
                ->label('Uložit SEO nastavení')
                ->icon('fal-floppy-disk')
                ->action('save'),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Spravuje výchozí title, description, canonical, Open Graph, X, JSON-LD, verifikační tagy a volitelné GA/GTM. Robots.txt a sitemap.xml jsou další položky stejné SEO sekce.';
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->seoSettings()->save(is_array($state) ? $state : []);
        $this->data = $this->seoSettings()->all();
        $this->form->fill($this->data);

        Notification::make()
            ->title('Globální SEO nastavení bylo uloženo')
            ->success()
            ->send();
    }

    private function renderWarnings(): HtmlString
    {
        return new HtmlString(view('mipress::filament.seo.health-warnings', [
            'warnings' => $this->seoSettings()->warnings($this->data),
        ])->render());
    }

    private function renderSerpPreview(): HtmlString
    {
        $seo = $this->seoResolver()->resolve([
            'url' => '/',
            'settings' => $this->data,
        ]);

        return new HtmlString(view('mipress::filament.seo.serp-preview', [
            'seo' => $seo,
        ])->render());
    }

    private function renderSocialPreview(): HtmlString
    {
        $seo = $this->seoResolver()->resolve([
            'title' => 'Ukázková služba',
            'title_is_final' => false,
            'url' => '/ukazkova-sluzba',
            'settings' => $this->data,
        ]);

        return new HtmlString(view('mipress::filament.seo.social-preview', [
            'seo' => $seo,
        ])->render());
    }

    private function seoSettings(): GlobalSeoSettingsManager
    {
        return app(GlobalSeoSettingsManager::class);
    }

    private function seoResolver(): SeoResolver
    {
        return app(SeoResolver::class);
    }
}
