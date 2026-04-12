<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use MiPress\Core\Filament\Clusters\WebCluster;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Setting;

/**
 * @property Schema $form
 */
class MediaConversionSettings extends Page
{
    private const HANDLE = 'media_conversions';

    /**
     * @var array<string, string>
     */
    private const MODE_OPTIONS = [
        'resize' => 'Bez ořezu',
        'crop' => 'Ořez',
        'crop_resize' => 'Ořez + zmenšení',
    ];

    /**
     * @var array<string, string>
     */
    private const GROUP_OPTIONS = [
        'thumbnails' => 'Thumbnails',
        'content' => 'Obsah',
        'social' => 'Sociální sítě',
        'hero' => 'Hero / bannery',
        'gallery' => 'Galerie',
        'system' => 'Systém',
        'other' => 'Ostatní',
    ];

    /**
     * @var array<string, string>
     */
    private const PRIORITY_OPTIONS = [
        'low' => 'Nízká',
        'normal' => 'Standard',
        'high' => 'Vysoká',
    ];

    /**
     * @var array<string, string>
     */
    private const BADGE_OPTIONS = [
        'thumbnail' => 'Thumbnail',
        'content' => 'Obsah',
        'listing' => 'Výpis',
        'hero' => 'Hero',
        'social' => 'Sociální',
        'system' => 'Systémová',
    ];

    /**
     * @var array<string, string>
     */
    private const CROP_STRATEGY_OPTIONS = [
        'none' => 'Bez fallbacku',
        'center' => 'Na střed',
        'focal_point' => 'Focal point',
        'manual' => 'Ruční crop',
    ];

    protected string $view = 'mipress::filament.pages.media-conversion-settings';

    protected static ?string $cluster = WebCluster::class;

    protected static ?string $slug = 'image-conversions';

    protected static string|\BackedEnum|null $navigationIcon = 'fal-images';

    protected static ?string $navigationLabel = 'Image konverze';

    protected static ?string $title = 'Správa image konverzí';

    protected static ?int $navigationSort = 40;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return EditSettings::canAccess();
    }

    public function mount(): void
    {
        $setting = $this->resolveSetting();

        $payload = is_array($setting->data) ? $setting->data : [];
        $payload['conversions'] = $this->normalizeConversions($payload['conversions'] ?? null);

        $this->data = $payload;
        $this->form->fill($payload);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Image konverze')
                    ->description('Každá konverze má vlastní identitu, výstupní režim a editor metadata. Přehled nahoře ukazuje nejdůležitější informace ještě před rozkliknutím detailu.')
                    ->schema([
                        Repeater::make('conversions')
                            ->label('')
                            ->addActionLabel('Přidat konverzi')
                            ->cloneable()
                            ->collapsible()
                            ->collapsed()
                            ->reorderable()
                            ->truncateItemLabel(false)
                            ->itemLabel(fn (array $state): Htmlable => $this->renderConversionItemLabel($state))
                            ->schema([
                                Placeholder::make('overview')
                                    ->hiddenLabel()
                                    ->content(fn (Get $get): Htmlable => $this->renderConversionOverview([
                                        'name' => $get('name'),
                                        'label' => $get('label'),
                                        'mode' => $get('mode'),
                                        'width' => $get('width'),
                                        'height' => $get('height'),
                                        'aspect_ratio' => $get('aspect_ratio'),
                                        'is_active' => $get('is_active'),
                                        'supports_focal_point' => $get('supports_focal_point'),
                                        'supports_manual_crop' => $get('supports_manual_crop'),
                                        'manual_crop_required' => $get('manual_crop_required'),
                                        'group' => $get('group'),
                                        'show_in_editor' => $get('show_in_editor'),
                                        'important' => $get('important'),
                                        'priority' => $get('priority'),
                                        'editor_badge' => $get('editor_badge'),
                                        'description' => $get('description'),
                                        'usage_context' => $get('usage_context'),
                                    ]))
                                    ->columnSpanFull(),
                                Tabs::make('conversion_tabs')
                                    ->contained(false)
                                    ->tabs([
                                        Tab::make('Základ')
                                            ->schema([
                                                Section::make('Identita konverze')
                                                    ->description('Krátké a srozumitelné informace, podle kterých editor pozná, k čemu daná varianta slouží.')
                                                    ->schema([
                                                        Grid::make([
                                                            'default' => 1,
                                                            'md' => 2,
                                                        ])->schema([
                                                            TextInput::make('label')
                                                                ->label('Název pro admin')
                                                                ->required()
                                                                ->maxLength(120)
                                                                ->placeholder('Miniatura článku')
                                                                ->helperText('Krátký název, který bude dobře čitelný i v přehledu více konverzí.')
                                                                ->live(onBlur: true),
                                                            TextInput::make('name')
                                                                ->label('Interní klíč')
                                                                ->required()
                                                                ->maxLength(100)
                                                                ->placeholder('thumbnail_square')
                                                                ->helperText('Bez mezer, ideálně malá písmena a podtržítka.')
                                                                ->rule('regex:/^[a-z0-9_]+$/')
                                                                ->live(onBlur: true),
                                                            Select::make('group')
                                                                ->label('Skupina')
                                                                ->options(self::GROUP_OPTIONS)
                                                                ->native(false)
                                                                ->searchable()
                                                                ->default('content')
                                                                ->helperText('Pomáhá držet pořádek mezi thumbnails, obsahem, sociálními sítěmi nebo hero výstupy.')
                                                                ->live(),
                                                            TextInput::make('sort_order')
                                                                ->label('Pořadí')
                                                                ->numeric()
                                                                ->default(0)
                                                                ->helperText('Nižší číslo = výše v editoru.')
                                                                ->live(onBlur: true),
                                                            Toggle::make('is_active')
                                                                ->label('Aktivní')
                                                                ->helperText('Neaktivní konverze zůstane v nastavení, ale nepoužije se v aktivních definicích.')
                                                                ->default(true)
                                                                ->live()
                                                                ->columnSpanFull(),
                                                            Textarea::make('description')
                                                                ->label('Krátký popis použití')
                                                                ->rows(2)
                                                                ->placeholder('Např. menší výstup pro výpisy článků nebo důležitý social share formát.')
                                                                ->columnSpanFull(),
                                                        ]),
                                                    ]),
                                            ]),
                                        Tab::make('Výstup / Rozměry')
                                            ->schema([
                                                Section::make('Režim konverze')
                                                    ->schema([
                                                        ToggleButtons::make('mode')
                                                            ->label('Režim výstupu')
                                                            ->options(self::MODE_OPTIONS)
                                                            ->colors([
                                                                'resize' => 'gray',
                                                                'crop' => 'warning',
                                                                'crop_resize' => 'primary',
                                                            ])
                                                            ->inline()
                                                            ->grouped()
                                                            ->required()
                                                            ->live()
                                                            ->helperText(fn (Get $get): string => $this->modeHelperText($get('mode')))
                                                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                                                if ($state === 'resize') {
                                                                    $this->resetCropSettings($set);

                                                                    return;
                                                                }

                                                                $this->syncAspectRatio($set, $get);

                                                                if (($get('default_crop_strategy') ?? 'none') === 'none') {
                                                                    $set('default_crop_strategy', 'focal_point');
                                                                }
                                                            })
                                                            ->columnSpanFull(),
                                                        Placeholder::make('output_note')
                                                            ->hiddenLabel()
                                                            ->content(fn (Get $get): Htmlable => new HtmlString($this->outputNote($get('mode'))))
                                                            ->columnSpanFull(),
                                                    ]),
                                                Section::make('Rozměry a poměr stran')
                                                    ->description(fn (Get $get): string => $this->dimensionsSectionDescription($get('mode')))
                                                    ->schema([
                                                        Grid::make([
                                                            'default' => 1,
                                                            'md' => 3,
                                                        ])->schema([
                                                            TextInput::make('width')
                                                                ->label('Šířka')
                                                                ->numeric()
                                                                ->required()
                                                                ->minValue(1)
                                                                ->suffix('px')
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn (Set $set, Get $get): bool => $this->syncAspectRatio($set, $get)),
                                                            TextInput::make('height')
                                                                ->label('Výška')
                                                                ->numeric()
                                                                ->minValue(1)
                                                                ->required(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                                ->helperText(fn (Get $get): string => $this->heightHelperText($get('mode')))
                                                                ->suffix('px')
                                                                ->live(onBlur: true)
                                                                ->afterStateUpdated(fn (Set $set, Get $get): bool => $this->syncAspectRatio($set, $get)),
                                                            Placeholder::make('ratio_preview')
                                                                ->label(fn (Get $get): string => $this->usesCropMode($get('mode'))
                                                                    ? 'Aktivní poměr stran'
                                                                    : 'Odvozený poměr stran')
                                                                ->content(fn (Get $get): string => $this->presentAspectRatio(
                                                                    $get('mode'),
                                                                    $get('aspect_ratio'),
                                                                    $get('width'),
                                                                    $get('height'),
                                                                )),
                                                            TextInput::make('aspect_ratio')
                                                                ->label('Poměr stran')
                                                                ->placeholder('např. 16:9')
                                                                ->helperText('U crop a thumbnail režimu je poměr stran součást definice výstupu.')
                                                                ->required(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                                ->rule('regex:/^\d+:\d+$/')
                                                                ->visible(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                                ->live(onBlur: true),
                                                            Placeholder::make('ratio_lock_note')
                                                                ->hiddenLabel()
                                                                ->visible(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                                ->content(fn (Get $get): Htmlable => new HtmlString($this->ratioLockNote(
                                                                    $get('mode'),
                                                                    $get('aspect_ratio'),
                                                                    $get('width'),
                                                                    $get('height'),
                                                                )))
                                                                ->columnSpan([
                                                                    'default' => 1,
                                                                    'md' => 2,
                                                                ]),
                                                        ]),
                                                    ]),
                                                Fieldset::make('Resize nastavení')
                                                    ->visible(fn (Get $get): bool => $this->usesResizeOutputMode($get('mode')))
                                                    ->schema([
                                                        Toggle::make('allow_upscale')
                                                            ->label('Povolit zvětšení menší předlohy')
                                                            ->helperText('Nechte vypnuté, pokud má být výstup bezpečně bez umělého zvětšování.')
                                                            ->default(false),
                                                    ]),
                                            ]),
                                        Tab::make('Ořez a kompozice')
                                            ->visible(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                            ->schema([
                                                Section::make('Kompozice výřezu')
                                                    ->description(fn (Get $get): string => $this->cropIntro($get('mode')))
                                                    ->schema([
                                                        Grid::make([
                                                            'default' => 1,
                                                            'md' => 2,
                                                        ])->schema([
                                                            Toggle::make('supports_focal_point')
                                                                ->label('Používá focal point')
                                                                ->helperText('Konverze počítá s řízením kompozice přes focal point.')
                                                                ->default(true)
                                                                ->live()
                                                                ->afterStateUpdated(function (?bool $state, Set $set, Get $get): void {
                                                                    if (($state === false) && (($get('default_crop_strategy') ?? null) === 'focal_point')) {
                                                                        $set('default_crop_strategy', 'center');
                                                                    }
                                                                }),
                                                            Toggle::make('supports_manual_crop')
                                                                ->label('Umožňuje ruční crop')
                                                                ->helperText('Editor může pro tuto konverzi upravit výřez ručně.')
                                                                ->default(false)
                                                                ->live()
                                                                ->afterStateUpdated(function (?bool $state, Set $set, Get $get): void {
                                                                    if ($state === false) {
                                                                        $set('manual_crop_required', false);

                                                                        if (($get('default_crop_strategy') ?? null) === 'manual') {
                                                                            $set('default_crop_strategy', 'center');
                                                                        }
                                                                    }
                                                                }),
                                                            Toggle::make('manual_crop_required')
                                                                ->label('Vyžaduje ruční crop')
                                                                ->helperText('Bez ručního ořezu nebude tato konverze považována za připravenou.')
                                                                ->default(false)
                                                                ->visible(fn (Get $get): bool => (bool) $get('supports_manual_crop'))
                                                                ->live()
                                                                ->afterStateUpdated(function (?bool $state, Set $set): void {
                                                                    if ($state) {
                                                                        $set('supports_manual_crop', true);
                                                                        $set('default_crop_strategy', 'manual');
                                                                    }
                                                                }),
                                                            Placeholder::make('crop_tools_note')
                                                                ->hiddenLabel()
                                                                ->content(fn (Get $get): Htmlable => new HtmlString($this->cropToolsNote([
                                                                    'mode' => $get('mode'),
                                                                    'supports_focal_point' => $get('supports_focal_point'),
                                                                    'supports_manual_crop' => $get('supports_manual_crop'),
                                                                    'manual_crop_required' => $get('manual_crop_required'),
                                                                ])))
                                                                ->columnSpanFull(),
                                                        ]),
                                                    ]),
                                                Section::make('Výchozí chování')
                                                    ->schema([
                                                        ToggleButtons::make('default_crop_strategy')
                                                            ->label('Fallback strategie')
                                                            ->options(fn (Get $get): array => $this->cropStrategyOptions(
                                                                $get('mode'),
                                                                $get('supports_focal_point'),
                                                                $get('supports_manual_crop'),
                                                                $get('manual_crop_required'),
                                                            ))
                                                            ->colors([
                                                                'none' => 'gray',
                                                                'center' => 'gray',
                                                                'focal_point' => 'info',
                                                                'manual' => 'warning',
                                                            ])
                                                            ->inline()
                                                            ->grouped()
                                                            ->default('focal_point')
                                                            ->live()
                                                            ->helperText(fn (Get $get): string => $this->cropStrategyHelperText(
                                                                $get('mode'),
                                                                $get('supports_focal_point'),
                                                                $get('supports_manual_crop'),
                                                                $get('manual_crop_required'),
                                                            ))
                                                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                                                if ($state === 'manual') {
                                                                    $set('supports_manual_crop', true);
                                                                }

                                                                if ($state === 'focal_point') {
                                                                    $set('supports_focal_point', true);
                                                                }
                                                            })
                                                            ->columnSpanFull(),
                                                        Placeholder::make('crop_strategy_note')
                                                            ->hiddenLabel()
                                                            ->content(fn (Get $get): Htmlable => new HtmlString($this->cropStrategyNote(
                                                                $get('default_crop_strategy'),
                                                                $get('manual_crop_required'),
                                                            )))
                                                            ->columnSpanFull(),
                                                    ]),
                                            ]),
                                        Tab::make('Editor / UI metadata')
                                            ->schema([
                                                Section::make('Viditelnost v editoru')
                                                    ->schema([
                                                        Grid::make([
                                                            'default' => 1,
                                                            'md' => 2,
                                                        ])->schema([
                                                            Toggle::make('show_in_editor')
                                                                ->label('Zobrazit v editoru')
                                                                ->helperText('Konverze se nabídne editorovi při práci s médiem.')
                                                                ->default(true)
                                                                ->live(),
                                                            Toggle::make('important')
                                                                ->label('Důležitá konverze')
                                                                ->helperText('Zvýrazní se mezi klíčovými výstupy.')
                                                                ->default(false)
                                                                ->live()
                                                                ->afterStateUpdated(function (?bool $state, Set $set, Get $get): void {
                                                                    if ($state && ($get('priority') !== 'high')) {
                                                                        $set('priority', 'high');
                                                                    }

                                                                    if (! $state && ($get('priority') === 'high')) {
                                                                        $set('priority', 'normal');
                                                                    }
                                                                }),
                                                            ToggleButtons::make('priority')
                                                                ->label('Priorita')
                                                                ->options(self::PRIORITY_OPTIONS)
                                                                ->colors([
                                                                    'low' => 'gray',
                                                                    'normal' => 'primary',
                                                                    'high' => 'warning',
                                                                ])
                                                                ->inline()
                                                                ->grouped()
                                                                ->default('normal')
                                                                ->live()
                                                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                                                    if ($state === 'high') {
                                                                        $set('important', true);
                                                                    }
                                                                })
                                                                ->columnSpanFull(),
                                                        ]),
                                                    ]),
                                                Section::make('Texty pro editora')
                                                    ->description('Krátké texty a badge pomáhají editorovi rychle pochopit, kdy tuto konverzi použít.')
                                                    ->schema([
                                                        Select::make('editor_badge')
                                                            ->label('Badge / typ použití')
                                                            ->options(self::BADGE_OPTIONS)
                                                            ->native(false)
                                                            ->searchable()
                                                            ->placeholder('Vyberte typ použití')
                                                            ->live(),
                                                        Textarea::make('editor_help_text')
                                                            ->label('Help text pro editora')
                                                            ->rows(2)
                                                            ->placeholder('Např. používat hlavně tam, kde je důležitá kontrola kompozice.')
                                                            ->columnSpanFull(),
                                                        Textarea::make('usage_context')
                                                            ->label('Použití v systému')
                                                            ->rows(2)
                                                            ->placeholder('Např. výpis článků, hero banner, OG image.')
                                                            ->columnSpanFull(),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
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
                ->icon('fal-floppy-disk')
                ->action('save'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $setting = $this->resolveSetting();

        $setting->data = [
            'conversions' => $this->normalizeConversions($state['conversions'] ?? null),
        ];

        $setting->save();

        Notification::make()
            ->title('Nastavení konverzí bylo uloženo')
            ->body('Přehled a chování image konverzí v administraci bylo úspěšně aktualizováno.')
            ->success()
            ->send();
    }

    private function resolveSetting(): Setting
    {
        return Setting::query()->firstOrCreate(
            ['handle' => self::HANDLE],
            [
                'name' => 'Image konverze',
                'icon' => 'fal-images',
                'sort_order' => 40,
                'data' => [],
            ],
        );
    }

    /**
     * @param  mixed  $conversions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConversions(mixed $conversions): array
    {
        if (! is_array($conversions) || $conversions === []) {
            return $this->defaultConversions();
        }

        $normalized = [];

        foreach (array_values($conversions) as $index => $conversion) {
            if (! is_array($conversion)) {
                continue;
            }

            $normalized[] = $this->normalizeConversion($conversion, $index);
        }

        return $normalized === [] ? $this->defaultConversions() : $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultConversions(): array
    {
        return collect(MediaConfig::builtInConversions())
            ->values()
            ->map(fn (array $conversion, int $index): array => $this->normalizeConversion([
                'name' => $conversion['name'] ?? 'conversion_'.$index,
                'label' => $conversion['label'] ?? 'Konverze '.($index + 1),
                'description' => $this->defaultDescriptionForGroup($this->defaultGroupFor($conversion), $conversion['mode'] ?? 'resize'),
                'is_active' => true,
                'sort_order' => $index + 1,
                'group' => $this->defaultGroupFor($conversion),
                'mode' => $conversion['name'] === 'thumbnail'
                    ? 'crop_resize'
                    : ($conversion['mode'] ?? 'resize'),
                'width' => $conversion['w'] ?? null,
                'height' => $conversion['h'] ?? null,
                'aspect_ratio' => $this->deriveAspectRatio($conversion['w'] ?? null, $conversion['h'] ?? null),
                'allow_upscale' => false,
                'supports_focal_point' => ($conversion['mode'] ?? 'resize') === 'crop',
                'supports_manual_crop' => ($conversion['mode'] ?? 'resize') === 'crop',
                'manual_crop_required' => false,
                'default_crop_strategy' => ($conversion['mode'] ?? 'resize') === 'crop' ? 'focal_point' : 'none',
                'show_in_editor' => true,
                'important' => in_array($conversion['name'] ?? '', ['thumbnail', 'og'], true),
                'priority' => in_array($conversion['name'] ?? '', ['thumbnail', 'og'], true) ? 'high' : 'normal',
                'editor_badge' => $this->defaultBadgeFor($conversion),
                'editor_help_text' => $this->defaultEditorHelpText($conversion),
                'usage_context' => $this->defaultUsageContext($conversion),
            ], $index))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $conversion
     * @return array<string, mixed>
     */
    private function normalizeConversion(array $conversion, int $index): array
    {
        $mode = (string) ($conversion['mode'] ?? 'resize');
        $height = $conversion['height'] ?? $conversion['h'] ?? null;
        $width = $conversion['width'] ?? $conversion['w'] ?? null;
        $normalizedMode = in_array($mode, array_keys(self::MODE_OPTIONS), true) ? $mode : 'resize';
        $supportsCrop = $this->usesCropMode($normalizedMode);
        $supportsFocalPoint = $supportsCrop ? (bool) ($conversion['supports_focal_point'] ?? true) : false;
        $supportsManualCrop = $supportsCrop ? (bool) ($conversion['supports_manual_crop'] ?? false) : false;
        $manualCropRequired = $supportsCrop ? (bool) ($conversion['manual_crop_required'] ?? false) : false;

        if ($manualCropRequired) {
            $supportsManualCrop = true;
        }

        $aspectRatio = (string) ($conversion['aspect_ratio'] ?? $this->deriveAspectRatio($width, $height) ?? '');
        $defaultCropStrategy = $this->normalizeCropStrategy(
            (string) ($conversion['default_crop_strategy'] ?? ($supportsCrop ? 'focal_point' : 'none')),
            $supportsCrop,
            $supportsFocalPoint,
            $supportsManualCrop,
            $manualCropRequired,
        );

        return [
            'name' => (string) ($conversion['name'] ?? 'conversion_'.$index),
            'label' => (string) ($conversion['label'] ?? 'Konverze '.($index + 1)),
            'description' => (string) ($conversion['description'] ?? ''),
            'is_active' => (bool) ($conversion['is_active'] ?? true),
            'sort_order' => (int) ($conversion['sort_order'] ?? ($index + 1)),
            'group' => (string) ($conversion['group'] ?? 'content'),
            'mode' => $normalizedMode,
            'width' => is_numeric($width) ? (int) $width : null,
            'height' => is_numeric($height) ? (int) $height : null,
            'aspect_ratio' => $aspectRatio,
            'allow_upscale' => (bool) ($conversion['allow_upscale'] ?? false),
            'supports_focal_point' => $supportsFocalPoint,
            'supports_manual_crop' => $supportsManualCrop,
            'manual_crop_required' => $manualCropRequired,
            'default_crop_strategy' => $defaultCropStrategy,
            'show_in_editor' => (bool) ($conversion['show_in_editor'] ?? true),
            'important' => (bool) ($conversion['important'] ?? false),
            'priority' => (string) ($conversion['priority'] ?? 'normal'),
            'editor_badge' => (string) ($conversion['editor_badge'] ?? ''),
            'editor_help_text' => (string) ($conversion['editor_help_text'] ?? ''),
            'usage_context' => (string) ($conversion['usage_context'] ?? ''),
        ];
    }

    private function usesCropMode(mixed $mode): bool
    {
        return in_array((string) $mode, ['crop', 'crop_resize'], true);
    }

    private function usesResizeOutputMode(mixed $mode): bool
    {
        return in_array((string) $mode, ['resize', 'crop_resize'], true);
    }

    private function resetCropSettings(Set $set): void
    {
        $set('supports_focal_point', false);
        $set('supports_manual_crop', false);
        $set('manual_crop_required', false);
        $set('default_crop_strategy', 'none');
    }

    private function syncAspectRatio(Set $set, Get $get): bool
    {
        if (! $this->usesCropMode($get('mode'))) {
            return true;
        }

        if (blank($get('aspect_ratio')) && filled($ratio = $this->deriveAspectRatio($get('width'), $get('height')))) {
            $set('aspect_ratio', $ratio);
        }

        return true;
    }

    private function modeHelperText(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop' => 'Ořez podle pevného poměru stran. Prioritou je kompozice výřezu.',
            'crop_resize' => 'Ořez + finální zmenšení. Typický workflow pro thumbnail a výpisové náhledy.',
            default => 'Bez ořezu. Obrázek se jen zmenší nebo přizpůsobí cílové velikosti.',
        };
    }

    private function dimensionsSectionDescription(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop' => 'Poměr stran je pro tuto konverzi pevný a určuje kompozici výřezu.',
            'crop_resize' => 'Nejdřív se hlídá kompozice výřezu, potom se dopočítá finální velikost.',
            default => 'U režimu bez ořezu může výška zůstat volitelná, pokud se má zachovat proporce originálu.',
        };
    }

    private function heightHelperText(mixed $mode): string
    {
        return $this->usesCropMode($mode)
            ? 'U režimů s ořezem je výška součástí pevného výstupu.'
            : 'Volitelné. U resize může zůstat prázdná pro proporční přizpůsobení.';
    }

    private function deriveAspectRatio(mixed $width, mixed $height): ?string
    {
        if (! is_numeric($width) || ! is_numeric($height)) {
            return null;
        }

        $normalizedWidth = (int) $width;
        $normalizedHeight = (int) $height;

        if ($normalizedWidth <= 0 || $normalizedHeight <= 0) {
            return null;
        }

        $divisor = $this->greatestCommonDivisor($normalizedWidth, $normalizedHeight);

        return (int) ($normalizedWidth / $divisor).':'.(int) ($normalizedHeight / $divisor);
    }

    private function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return max($left, 1);
    }

    private function presentAspectRatio(mixed $mode, mixed $aspectRatio, mixed $width, mixed $height): string
    {
        $explicitRatio = is_string($aspectRatio) ? trim($aspectRatio) : '';
        $derivedRatio = $this->deriveAspectRatio($width, $height);

        if ($this->usesCropMode($mode)) {
            return $explicitRatio !== '' ? $explicitRatio : ($derivedRatio ?? 'Doplňte šířku a výšku');
        }

        return $derivedRatio ?? 'Poměr zůstává odvozený z předlohy nebo cílových rozměrů';
    }

    private function compactAspectRatio(mixed $aspectRatio, mixed $width, mixed $height): ?string
    {
        $explicitRatio = is_string($aspectRatio) ? trim($aspectRatio) : '';

        if ($explicitRatio !== '') {
            return $explicitRatio;
        }

        return $this->deriveAspectRatio($width, $height);
    }

    private function ratioLockNote(mixed $mode, mixed $aspectRatio, mixed $width, mixed $height): string
    {
        if (! $this->usesCropMode($mode)) {
            return '';
        }

        $ratio = $this->compactAspectRatio($aspectRatio, $width, $height);

        if (! filled($ratio)) {
            return '<p class="text-sm text-gray-600 dark:text-gray-300">Doplňte šířku, výšku nebo poměr stran. Cropper se pak uzamkne na pevný poměr této konverze.</p>';
        }

        return '<p class="text-sm text-gray-600 dark:text-gray-300">Cropper bude pro tuto konverzi uzamčený na poměr <span class="font-semibold text-gray-950 dark:text-white">'.e($ratio).'</span>.</p>';
    }

    private function dimensionsSummary(array $state): string
    {
        $width = is_numeric($state['width'] ?? null) ? (int) $state['width'] : null;
        $height = is_numeric($state['height'] ?? null) ? (int) $state['height'] : null;

        if (($width === null) && ($height === null)) {
            return 'Rozměry nejsou vyplněné';
        }

        if ($width !== null && $height !== null) {
            return $width.' × '.$height.' px';
        }

        return ($width ?? $height).' px';
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    private function cropCapabilityPills(array $state): array
    {
        $pills = [];

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['supports_focal_point'] ?? false)) {
            $pills[] = $this->summaryPill('FP', 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300');
        }

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['supports_manual_crop'] ?? false)) {
            $pills[] = $this->summaryPill('MC', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300');
        }

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['manual_crop_required'] ?? false)) {
            $pills[] = $this->summaryPill('Required', 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300');
        }

        return $pills;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    private function conversionStatusPills(array $state): array
    {
        $pills = [];

        $pills[] = $this->summaryPill((bool) ($state['show_in_editor'] ?? true) ? 'V editoru' : 'Skryto', (bool) ($state['show_in_editor'] ?? true)
            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
            : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200');

        if ((bool) ($state['important'] ?? false)) {
            $pills[] = $this->summaryPill('Důležitá', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300');
        }

        if (($state['priority'] ?? 'normal') === 'high') {
            $pills[] = $this->summaryPill('Priorita', 'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300');
        }

        if (! (bool) ($state['is_active'] ?? true)) {
            $pills[] = $this->summaryPill('Neaktivní', 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200');
        }

        return $pills;
    }

    private function renderConversionItemLabel(array $state): Htmlable
    {
        $title = trim((string) ($state['label'] ?? ''));
        $fallbackTitle = trim((string) ($state['name'] ?? ''));
        $title = $title !== '' ? $title : ($fallbackTitle !== '' ? $fallbackTitle : 'Nová konverze');

        $segments = [
            '<span class="font-medium text-gray-950 dark:text-white">'.e($title).'</span>',
            $this->summaryPill($this->modeSummaryLabel($state['mode'] ?? null), $this->modePillClasses($state['mode'] ?? null)),
        ];

        if (filled($state['group'] ?? null)) {
            $segments[] = $this->summaryPill($this->groupLabel($state['group']), 'bg-white text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700');
        }

        if (filled($editorBadge = $this->editorBadgeLabel($state['editor_badge'] ?? null))) {
            $segments[] = $this->summaryPill($editorBadge, 'bg-lime-50 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300');
        }

        $segments[] = '<span class="text-xs text-gray-500 dark:text-gray-400">'.e($this->dimensionsSummary($state)).'</span>';

        if (filled($ratio = $this->compactAspectRatio($state['aspect_ratio'] ?? null, $state['width'] ?? null, $state['height'] ?? null))) {
            $segments[] = $this->summaryPill($ratio, 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300');
        }

        foreach ($this->cropCapabilityPills($state) as $pill) {
            $segments[] = $pill;
        }

        if (! (bool) ($state['show_in_editor'] ?? true)) {
            $segments[] = $this->summaryPill('Skryto', 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200');
        }

        if (! (bool) ($state['is_active'] ?? true)) {
            $segments[] = $this->summaryPill('Neaktivní', 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200');
        }

        return new HtmlString('<span class="inline-flex flex-wrap items-center gap-2">'.implode('', $segments).'</span>');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function renderConversionOverview(array $state): Htmlable
    {
        $title = trim((string) ($state['label'] ?? ''));
        $title = $title !== '' ? $title : (trim((string) ($state['name'] ?? '')) ?: 'Nová konverze');

        $contextBits = [
            '<span class="text-xs text-gray-500 dark:text-gray-400">'.e($this->dimensionsSummary($state)).'</span>',
        ];

        if (filled($ratio = $this->compactAspectRatio($state['aspect_ratio'] ?? null, $state['width'] ?? null, $state['height'] ?? null))) {
            $contextBits[] = '<span class="text-xs text-gray-500 dark:text-gray-400">•</span>';
            $contextBits[] = '<span class="text-xs text-gray-500 dark:text-gray-400">'.e($ratio).'</span>';
        }

        if (filled($state['usage_context'] ?? null)) {
            $contextBits[] = '<span class="text-xs text-gray-500 dark:text-gray-400">•</span>';
            $contextBits[] = '<span class="text-xs text-gray-500 dark:text-gray-400">'.e((string) $state['usage_context']).'</span>';
        }

        $lines = [
            '<div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40">',
            '<div class="space-y-1">',
            '<div class="flex flex-wrap items-center gap-2">',
            '<span class="text-sm font-semibold text-gray-950 dark:text-white">'.e($title).'</span>',
            $this->summaryPill($this->modeSummaryLabel($state['mode'] ?? null), $this->modePillClasses($state['mode'] ?? null)),
            filled($state['group'] ?? null)
                ? $this->summaryPill($this->groupLabel($state['group']), 'bg-white text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700')
                : '',
            filled($editorBadge = $this->editorBadgeLabel($state['editor_badge'] ?? null))
                ? $this->summaryPill($editorBadge, 'bg-lime-50 text-lime-700 dark:bg-lime-900/30 dark:text-lime-300')
                : '',
            '</div>',
            filled($state['description'] ?? null)
                ? '<p class="text-sm text-gray-600 dark:text-gray-300">'.e((string) $state['description']).'</p>'
                : '',
            '<div class="flex flex-wrap items-center gap-2">'.implode('', $contextBits).'</div>',
            '</div>',
            '<div class="flex flex-wrap items-center gap-2">',
            ...$this->cropCapabilityPills($state),
            ...$this->conversionStatusPills($state),
            '</div>',
            '</div>',
        ];

        return new HtmlString(implode('', array_filter($lines)));
    }

    private function summaryPill(string $label, string $classes): string
    {
        return '<span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-medium '.$classes.'">'.e($label).'</span>';
    }

    private function modeSummaryLabel(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop_resize' => 'Thumbnail',
            'crop' => 'Ořez',
            default => 'Bez ořezu',
        };
    }

    private function modeLabel(mixed $mode): string
    {
        return self::MODE_OPTIONS[(string) $mode] ?? 'Bez ořezu';
    }

    private function groupLabel(mixed $group): string
    {
        return self::GROUP_OPTIONS[(string) $group] ?? (string) $group;
    }

    private function editorBadgeLabel(mixed $badge): ?string
    {
        $label = self::BADGE_OPTIONS[(string) $badge] ?? null;

        return filled($label) ? $label : null;
    }

    private function modePillClasses(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            'crop_resize' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            default => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        };
    }

    private function outputNote(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop' => '<p class="text-sm text-gray-600 dark:text-gray-300">Pevný poměr stran a důraz na kompozici. Editor řeší hlavně to, co ve výřezu zůstane.</p>',
            'crop_resize' => '<p class="text-sm text-gray-600 dark:text-gray-300">Typický thumbnail workflow. Nejdřív se určí kompozice, potom se vypočítá finální velikost.</p>',
            default => '<p class="text-sm text-gray-600 dark:text-gray-300">Jednoduchý výstup bez ořezu. Crop, focal point ani ruční výřez se v tomto režimu záměrně nezobrazují.</p>',
        };
    }

    private function cropIntro(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop_resize' => 'Thumbnail režim: kompozice je klíčová a cropper má být vždy svázaný s pevným poměrem stran této konverze.',
            default => 'Tato konverze používá ořez podle pevného poměru stran. Níže nastavíte focal point, ruční crop a výchozí fallback.',
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function cropToolsNote(array $state): string
    {
        if ((bool) ($state['manual_crop_required'] ?? false)) {
            return '<p class="text-sm text-gray-600 dark:text-gray-300">Ruční crop je pro tuto konverzi povinný. Editor musí výřez potvrdit přímo pro tuto variantu.</p>';
        }

        $parts = [];

        if ((bool) ($state['supports_focal_point'] ?? false)) {
            $parts[] = 'Focal point může řídit kompozici automaticky.';
        }

        if ((bool) ($state['supports_manual_crop'] ?? false)) {
            $parts[] = 'Editor může výřez kdykoliv upravit ručně.';
        }

        if ($parts === []) {
            $parts[] = 'Pokud nechcete řídit kompozici přes focal point ani ruční crop, ponechte fallback na střed nebo bez fallbacku.';
        }

        return '<p class="text-sm text-gray-600 dark:text-gray-300">'.e(implode(' ', $parts)).'</p>';
    }

    /**
     * @return array<string, string>
     */
    private function cropStrategyOptions(mixed $mode, mixed $supportsFocalPoint, mixed $supportsManualCrop, mixed $manualCropRequired): array
    {
        if (! $this->usesCropMode($mode)) {
            return [];
        }

        if ((bool) $manualCropRequired) {
            return [
                'manual' => self::CROP_STRATEGY_OPTIONS['manual'],
            ];
        }

        $options = [
            'none' => self::CROP_STRATEGY_OPTIONS['none'],
            'center' => self::CROP_STRATEGY_OPTIONS['center'],
        ];

        if ((bool) $supportsFocalPoint) {
            $options['focal_point'] = self::CROP_STRATEGY_OPTIONS['focal_point'];
        }

        if ((bool) $supportsManualCrop) {
            $options['manual'] = self::CROP_STRATEGY_OPTIONS['manual'];
        }

        return $options;
    }

    private function cropStrategyHelperText(mixed $mode, mixed $supportsFocalPoint, mixed $supportsManualCrop, mixed $manualCropRequired): string
    {
        if (! $this->usesCropMode($mode)) {
            return '';
        }

        if ((bool) $manualCropRequired) {
            return 'Ruční crop je povinný, takže fallback se zamyká na ruční výřez.';
        }

        if (! (bool) $supportsFocalPoint && ! (bool) $supportsManualCrop) {
            return 'Určuje chování ve chvíli, kdy konverze nemá focal point ani ruční crop.';
        }

        return 'Určuje, co se použije, když editor nevytvoří vlastní ruční výřez.';
    }

    private function cropStrategyNote(mixed $strategy, mixed $manualCropRequired): string
    {
        if ((bool) $manualCropRequired) {
            return '<p class="text-sm text-gray-600 dark:text-gray-300">Bez ručního cropu není tato konverze připravená. To je vhodné hlavně pro důležité thumbnail nebo hero výstupy.</p>';
        }

        return match ((string) $strategy) {
            'center' => '<p class="text-sm text-gray-600 dark:text-gray-300">Pokud editor nic nenastaví, použije se středový výřez.</p>',
            'focal_point' => '<p class="text-sm text-gray-600 dark:text-gray-300">Pokud editor nic nenastaví ručně, výřez se bude řídit focal pointem média.</p>',
            'manual' => '<p class="text-sm text-gray-600 dark:text-gray-300">Preferovaný je ruční crop pro tuto konkrétní konverzi.</p>',
            default => '<p class="text-sm text-gray-600 dark:text-gray-300">Bez fallbacku. Kompozici drží explicitní nastavení této konverze.</p>',
        };
    }

    private function normalizeCropStrategy(
        string $strategy,
        bool $supportsCrop,
        bool $supportsFocalPoint,
        bool $supportsManualCrop,
        bool $manualCropRequired,
    ): string {
        if (! $supportsCrop) {
            return 'none';
        }

        if ($manualCropRequired) {
            return 'manual';
        }

        $allowed = array_keys($this->cropStrategyOptions('crop', $supportsFocalPoint, $supportsManualCrop, $manualCropRequired));

        if (in_array($strategy, $allowed, true)) {
            return $strategy;
        }

        if (in_array('focal_point', $allowed, true)) {
            return 'focal_point';
        }

        if (in_array('center', $allowed, true)) {
            return 'center';
        }

        return 'none';
    }

    /**
     * @param  array{name?: string, label?: string, mode?: string}  $conversion
     */
    private function defaultGroupFor(array $conversion): string
    {
        return match ((string) ($conversion['name'] ?? '')) {
            'thumbnail' => 'thumbnails',
            'og' => 'social',
            default => 'content',
        };
    }

    /**
     * @param  array{name?: string}  $conversion
     */
    private function defaultBadgeFor(array $conversion): string
    {
        return match ((string) ($conversion['name'] ?? '')) {
            'thumbnail' => 'thumbnail',
            'og' => 'social',
            'large', 'medium' => 'content',
            default => 'system',
        };
    }

    private function defaultDescriptionForGroup(string $group, string $mode): string
    {
        return match ($group) {
            'thumbnails' => $mode === 'resize'
                ? 'Menší výstup pro přehledy a rychlé načítání.'
                : 'Kompaktní výstup pro výpisy, kde je důležitá kontrola kompozice.',
            'social' => 'Sdílecí výstup pro sociální sítě a Open Graph.',
            default => 'Běžná výstupní varianta obrázku pro administraci a frontend.',
        };
    }

    /**
     * @param  array{name?: string, label?: string}  $conversion
     */
    private function defaultEditorHelpText(array $conversion): string
    {
        return match ((string) ($conversion['name'] ?? '')) {
            'thumbnail' => 'Použijte tam, kde má editor největší kontrolu nad kompozicí výřezu.',
            'og' => 'Důležitý výstup pro sdílení. Hlídejte bezpečný výřez a čitelnost obsahu.',
            default => 'Standardní systémová varianta bez potřeby speciálního zásahu editora.',
        };
    }

    /**
     * @param  array{name?: string, label?: string}  $conversion
     */
    private function defaultUsageContext(array $conversion): string
    {
        return match ((string) ($conversion['name'] ?? '')) {
            'thumbnail' => 'Výpis článků, karty obsahu, menší přehledové bloky.',
            'medium' => 'Běžný obsah stránky a průběžné ilustrační obrázky.',
            'large' => 'Výraznější obsahové bloky a větší layouty.',
            'og' => 'OG image při sdílení stránek a obsahu.',
            default => 'Použití v systému doplňte podle potřeby týmu.',
        };
    }
}
