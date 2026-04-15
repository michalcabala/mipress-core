<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
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

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 10;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'fal-magnifying-glass';
    }

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.pages.global_seo.navigation_label');
    }

    public function getTitle(): string|Htmlable
    {
        return __('mipress::admin.pages.global_seo.title');
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
                Section::make(__('mipress::admin.pages.global_seo.sections.default_metadata'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.default_metadata'))
                    ->schema([
                        TextInput::make('metadata.default_title')
                            ->label(__('mipress::admin.pages.global_seo.fields.default_title'))
                            ->placeholder('MiPress Studio')
                            ->live(onBlur: true),
                        TextInput::make('metadata.homepage_title')
                            ->label(__('mipress::admin.pages.global_seo.fields.homepage_title'))
                            ->placeholder('MiPress Studio | Tvorba webů')
                            ->helperText(__('mipress::admin.pages.global_seo.help.homepage_title'))
                            ->live(onBlur: true),
                        TextInput::make('metadata.title_suffix')
                            ->label(__('mipress::admin.pages.global_seo.fields.title_suffix'))
                            ->placeholder(' | MiPress Studio')
                            ->helperText(__('mipress::admin.pages.global_seo.help.title_suffix'))
                            ->live(onBlur: true),
                        Textarea::make('metadata.default_description')
                            ->label(__('mipress::admin.pages.global_seo.fields.default_description'))
                            ->rows(4)
                            ->maxLength(180)
                            ->helperText(__('mipress::admin.pages.global_seo.help.default_description'))
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.global_seo.sections.canonical_and_locale'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.canonical_and_locale'))
                    ->schema([
                        TextInput::make('canonical.base_url')
                            ->label(__('mipress::admin.pages.global_seo.fields.canonical_base_url'))
                            ->placeholder('https://www.example.cz')
                            ->url()
                            ->live(onBlur: true),
                        Select::make('canonical.trailing_slash')
                            ->label(__('mipress::admin.pages.global_seo.fields.trailing_slash'))
                            ->options([
                                'keep' => __('mipress::admin.pages.global_seo.trailing_slash.keep'),
                                'add' => __('mipress::admin.pages.global_seo.trailing_slash.add'),
                                'remove' => __('mipress::admin.pages.global_seo.trailing_slash.remove'),
                            ])
                            ->default('keep'),
                        Toggle::make('canonical.strip_query_parameters')
                            ->label(__('mipress::admin.pages.global_seo.fields.strip_query_parameters'))
                            ->default(true),
                        Toggle::make('canonical.force_https')
                            ->label(__('mipress::admin.pages.global_seo.fields.force_https'))
                            ->default(false),
                        TextInput::make('locale.html_lang')
                            ->label(__('mipress::admin.pages.global_seo.fields.html_lang'))
                            ->placeholder('cs')
                            ->helperText(__('mipress::admin.pages.global_seo.help.html_lang'))
                            ->live(onBlur: true),
                        TextInput::make('locale.og_locale')
                            ->label(__('mipress::admin.pages.global_seo.fields.og_locale'))
                            ->placeholder('cs_CZ')
                            ->helperText(__('mipress::admin.pages.global_seo.help.og_locale'))
                            ->live(onBlur: true),
                        TagsInput::make('locale.alternate_og_locales')
                            ->label(__('mipress::admin.pages.global_seo.fields.alternate_og_locales'))
                            ->placeholder('en_US')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.global_seo.sections.social'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.social'))
                    ->schema([
                        TextInput::make('open_graph.site_name')
                            ->label(__('mipress::admin.pages.global_seo.fields.og_site_name'))
                            ->placeholder('MiPress Studio')
                            ->helperText(__('mipress::admin.pages.global_seo.help.og_site_name'))
                            ->live(onBlur: true),
                        Select::make('twitter.card')
                            ->label(__('mipress::admin.pages.global_seo.fields.twitter_card'))
                            ->options([
                                'summary_large_image' => 'summary_large_image',
                                'summary' => 'summary',
                            ])
                            ->default('summary_large_image'),
                        CuratorPicker::make('open_graph.default_image_id')
                            ->label(__('mipress::admin.pages.global_seo.fields.default_og_image'))
                            ->helperText(__('mipress::admin.pages.global_seo.help.default_og_image'))
                            ->columnSpanFull(),
                        TextInput::make('open_graph.default_image_alt')
                            ->label(__('mipress::admin.pages.global_seo.fields.default_og_image_alt'))
                            ->placeholder('MiPress Studio')
                            ->columnSpanFull()
                            ->live(onBlur: true),
                        TextInput::make('twitter.site')
                            ->label(__('mipress::admin.pages.global_seo.fields.twitter_site'))
                            ->placeholder('@mipress')
                            ->helperText(__('mipress::admin.pages.global_seo.help.twitter_site'))
                            ->live(onBlur: true),
                        TextInput::make('twitter.creator')
                            ->label(__('mipress::admin.pages.global_seo.fields.twitter_creator'))
                            ->placeholder('@michal')
                            ->live(onBlur: true),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.global_seo.sections.structured_data'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.structured_data'))
                    ->schema([
                        Toggle::make('structured_data.enabled')
                            ->label(__('mipress::admin.pages.global_seo.fields.structured_data_enabled'))
                            ->default(true),
                        Select::make('structured_data.organization_type')
                            ->label(__('mipress::admin.pages.global_seo.fields.organization_type'))
                            ->options([
                                'Organization' => 'Organization',
                                'LocalBusiness' => 'LocalBusiness',
                            ])
                            ->default('Organization'),
                        TextInput::make('structured_data.organization_name')
                            ->label(__('mipress::admin.pages.global_seo.fields.organization_name'))
                            ->placeholder('MiPress Studio s.r.o.')
                            ->live(onBlur: true),
                        TextInput::make('structured_data.organization_url')
                            ->label(__('mipress::admin.pages.global_seo.fields.organization_url'))
                            ->placeholder('https://www.example.cz')
                            ->url()
                            ->live(onBlur: true),
                        CuratorPicker::make('structured_data.logo_id')
                            ->label(__('mipress::admin.pages.global_seo.fields.logo'))
                            ->columnSpanFull(),
                        TextInput::make('structured_data.phone')
                            ->label(__('mipress::admin.pages.global_seo.fields.phone')),
                        TextInput::make('structured_data.email')
                            ->label(__('mipress::admin.pages.global_seo.fields.email'))
                            ->email(),
                        TextInput::make('structured_data.street_address')
                            ->label(__('mipress::admin.pages.global_seo.fields.street_address')),
                        TextInput::make('structured_data.address_locality')
                            ->label(__('mipress::admin.pages.global_seo.fields.address_locality')),
                        TextInput::make('structured_data.postal_code')
                            ->label(__('mipress::admin.pages.global_seo.fields.postal_code')),
                        TextInput::make('structured_data.address_country')
                            ->label(__('mipress::admin.pages.global_seo.fields.address_country'))
                            ->placeholder('CZ'),
                        TagsInput::make('structured_data.same_as')
                            ->label(__('mipress::admin.pages.global_seo.fields.same_as'))
                            ->placeholder('https://www.facebook.com/mipress')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.global_seo.sections.verification_and_analytics'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.verification_and_analytics'))
                    ->schema([
                        TextInput::make('verification.google')
                            ->label(__('mipress::admin.pages.global_seo.fields.verification_google')),
                        TextInput::make('verification.bing')
                            ->label(__('mipress::admin.pages.global_seo.fields.verification_bing')),
                        TextInput::make('verification.seznam')
                            ->label(__('mipress::admin.pages.global_seo.fields.verification_seznam')),
                        TextInput::make('verification.facebook_domain')
                            ->label(__('mipress::admin.pages.global_seo.fields.verification_facebook_domain')),
                        TextInput::make('analytics.google_analytics_id')
                            ->label(__('mipress::admin.pages.global_seo.fields.analytics_google_analytics_id'))
                            ->placeholder('G-XXXXXXXXXX'),
                        TextInput::make('analytics.google_tag_manager_id')
                            ->label(__('mipress::admin.pages.global_seo.fields.analytics_google_tag_manager_id'))
                            ->placeholder('GTM-XXXXXXX'),
                    ])
                    ->columns(2),

                Section::make(__('mipress::admin.pages.global_seo.sections.preview_and_health'))
                    ->description(__('mipress::admin.pages.global_seo.descriptions.preview_and_health'))
                    ->schema([
                        TextEntry::make('health')
                            ->hiddenLabel()
                            ->state(fn (): HtmlString => $this->renderWarnings())
                            ->columnSpanFull(),
                        TextEntry::make('serp_preview')
                            ->hiddenLabel()
                            ->state(fn (): HtmlString => $this->renderSerpPreview()),
                        TextEntry::make('social_preview')
                            ->hiddenLabel()
                            ->state(fn (): HtmlString => $this->renderSocialPreview()),
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
                ->label(__('mipress::admin.pages.global_seo.actions.save'))
                ->icon('fal-floppy-disk')
                ->action('save'),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('mipress::admin.pages.global_seo.subheading');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->seoSettings()->save(is_array($state) ? $state : []);
        $this->data = $this->seoSettings()->all();
        $this->form->fill($this->data);

        Notification::make()
            ->title(__('mipress::admin.pages.global_seo.saved_title'))
            ->body(__('mipress::admin.pages.global_seo.saved_body'))
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
            'title' => __('mipress::admin.seo_preview.sample_title'),
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
