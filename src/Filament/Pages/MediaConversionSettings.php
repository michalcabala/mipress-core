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
                    ->description('Každá konverze má vlastní režim, rozměry a editor metadata. Přehled zůstává čitelný i při větším počtu variant.')
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
                                    ]))
                                    ->columnSpanFull(),
                                Tabs::make('conversion_tabs')
                                    ->tabs([
                                        Tab::make('Základ')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                ])->schema([
                                                    TextInput::make('name')
                                                        ->label('Interní klíč')
                                                        ->required()
                                                        ->maxLength(100)
                                                        ->placeholder('thumbnail_square')
                                                        ->helperText('Interní identifikátor bez mezer, ideálně malá písmena a podtržítka.')
                                                        ->rule('regex:/^[a-z0-9_]+$/')
                                                        ->live(onBlur: true),
                                                    TextInput::make('label')
                                                        ->label('Název pro admin')
                                                        ->required()
                                                        ->maxLength(120)
                                                        ->placeholder('Miniatura článku')
                                                        ->live(onBlur: true),
                                                    Textarea::make('description')
                                                        ->label('Krátký popis použití')
                                                        ->rows(2)
                                                        ->placeholder('Kde se konverze používá a co od ní editor čeká.')
                                                        ->columnSpanFull(),
                                                    ToggleButtons::make('mode')
                                                        ->label('Režim konverze')
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
                                                                $set('supports_focal_point', false);
                                                                $set('supports_manual_crop', false);
                                                                $set('manual_crop_required', false);
                                                                $set('default_crop_strategy', 'none');

                                                                return;
                                                            }

                                                            if (blank($get('aspect_ratio')) && filled($ratio = $this->deriveAspectRatio($get('width'), $get('height')))) {
                                                                $set('aspect_ratio', $ratio);
                                                            }

                                                            if (($get('default_crop_strategy') ?? 'none') === 'none') {
                                                                $set('default_crop_strategy', 'focal_point');
                                                            }
                                                        })
                                                        ->columnSpanFull(),
                                                    Toggle::make('is_active')
                                                        ->label('Aktivní')
                                                        ->default(true)
                                                        ->live(),
                                                    TextInput::make('sort_order')
                                                        ->label('Pořadí')
                                                        ->numeric()
                                                        ->default(0)
                                                        ->helperText('Nižší číslo = výše v editoru.')
                                                        ->live(onBlur: true),
                                                    Select::make('group')
                                                        ->label('Skupina')
                                                        ->options(self::GROUP_OPTIONS)
                                                        ->native(false)
                                                        ->searchable()
                                                        ->default('content')
                                                        ->live(),
                                                ]),
                                            ])
                                            ->columns(2),
                                        Tab::make('Výstup / Rozměry')
                                            ->schema([
                                                Fieldset::make('Výstupní parametry')
                                                    ->schema([
                                                        TextInput::make('width')
                                                            ->label('Šířka')
                                                            ->numeric()
                                                            ->required()
                                                            ->minValue(1)
                                                            ->suffix('px')
                                                            ->live(onBlur: true),
                                                        TextInput::make('height')
                                                            ->label('Výška')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->required(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                            ->helperText(fn (Get $get): string => $this->usesCropMode($get('mode'))
                                                                ? 'U režimů s ořezem je výška součástí pevného výstupu.'
                                                                : 'Volitelné. U resize může zůstat prázdná pro proporční přizpůsobení.')
                                                            ->suffix('px')
                                                            ->live(onBlur: true),
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
                                                            ->helperText('Klíčové pro crop a crop + resize. Cropper se podle něj zamkne.')
                                                            ->required(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                            ->rule('regex:/^\d+:\d+$/')
                                                            ->visible(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                                            ->live(onBlur: true),
                                                    ])
                                                    ->columns([
                                                        'default' => 1,
                                                        'md' => 3,
                                                    ]),
                                                Fieldset::make('Resize nastavení')
                                                    ->visible(fn (Get $get): bool => $this->usesResizeOutputMode($get('mode')))
                                                    ->schema([
                                                        Toggle::make('allow_upscale')
                                                            ->label('Povolit zvětšení menší předlohy')
                                                            ->helperText('Nechte vypnuté, pokud má být výstup vždy bezpečně bez umělého zvětšování.')
                                                            ->default(false),
                                                    ]),
                                                Placeholder::make('output_note')
                                                    ->hiddenLabel()
                                                    ->content(fn (Get $get): Htmlable => new HtmlString($this->outputNote($get('mode')))
                                                    )
                                                    ->columnSpanFull(),
                                            ]),
                                        Tab::make('Ořez a kompozice')
                                            ->visible(fn (Get $get): bool => $this->usesCropMode($get('mode')))
                                            ->schema([
                                                Placeholder::make('crop_intro')
                                                    ->hiddenLabel()
                                                    ->content(fn (Get $get): Htmlable => new HtmlString($this->cropIntro($get('mode'))))
                                                    ->columnSpanFull(),
                                                Fieldset::make('Focal point a ruční crop')
                                                    ->schema([
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
                                                            ->helperText('Bez ručního ořezu nebude konverze považována za připravenou.')
                                                            ->default(false)
                                                            ->visible(fn (Get $get): bool => (bool) $get('supports_manual_crop'))
                                                            ->live()
                                                            ->afterStateUpdated(function (?bool $state, Set $set): void {
                                                                if ($state) {
                                                                    $set('supports_manual_crop', true);
                                                                }
                                                            }),
                                                        Select::make('default_crop_strategy')
                                                            ->label('Fallback strategie')
                                                            ->options(self::CROP_STRATEGY_OPTIONS)
                                                            ->native(false)
                                                            ->default('focal_point')
                                                            ->live()
                                                            ->helperText('Použije se, když editor nevytvoří vlastní ruční crop.')
                                                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                                                if ($state === 'manual') {
                                                                    $set('supports_manual_crop', true);
                                                                }

                                                                if ($state === 'focal_point') {
                                                                    $set('supports_focal_point', true);
                                                                }
                                                            }),
                                                    ])
                                                    ->columns([
                                                        'default' => 1,
                                                        'md' => 2,
                                                    ]),
                                            ]),
                                        Tab::make('Editor / UI metadata')
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                ])->schema([
                                                    Toggle::make('show_in_editor')
                                                        ->label('Zobrazit v editoru médií')
                                                        ->helperText('Konverze se nabídne editorovi při práci s médiem.')
                                                        ->default(true)
                                                        ->live(),
                                                    Toggle::make('important')
                                                        ->label('Důležitá konverze')
                                                        ->helperText('Zvýrazní se mezi klíčovými výstupy.')
                                                        ->default(false)
                                                        ->live(),
                                                    Select::make('priority')
                                                        ->label('Priorita')
                                                        ->options(self::PRIORITY_OPTIONS)
                                                        ->native(false)
                                                        ->default('normal')
                                                        ->live(),
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
                                                        ->placeholder('Stručně vysvětlete, kdy tuto konverzi použít.')
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
        $supportsCrop = $this->usesCropMode($mode);

        return [
            'name' => (string) ($conversion['name'] ?? 'conversion_'.$index),
            'label' => (string) ($conversion['label'] ?? 'Konverze '.($index + 1)),
            'description' => (string) ($conversion['description'] ?? ''),
            'is_active' => (bool) ($conversion['is_active'] ?? true),
            'sort_order' => (int) ($conversion['sort_order'] ?? ($index + 1)),
            'group' => (string) ($conversion['group'] ?? 'content'),
            'mode' => in_array($mode, array_keys(self::MODE_OPTIONS), true) ? $mode : 'resize',
            'width' => is_numeric($width) ? (int) $width : null,
            'height' => is_numeric($height) ? (int) $height : null,
            'aspect_ratio' => (string) ($conversion['aspect_ratio'] ?? $this->deriveAspectRatio($width, $height) ?? ''),
            'allow_upscale' => (bool) ($conversion['allow_upscale'] ?? false),
            'supports_focal_point' => $supportsCrop ? (bool) ($conversion['supports_focal_point'] ?? true) : false,
            'supports_manual_crop' => $supportsCrop ? (bool) ($conversion['supports_manual_crop'] ?? false) : false,
            'manual_crop_required' => $supportsCrop ? (bool) ($conversion['manual_crop_required'] ?? false) : false,
            'default_crop_strategy' => $supportsCrop
                ? (string) ($conversion['default_crop_strategy'] ?? 'focal_point')
                : 'none',
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

    private function modeHelperText(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop' => 'Ořez podle pevného poměru stran. Prioritou je kompozice výstupu.',
            'crop_resize' => 'Nejprve ořez podle poměru stran, potom finální zmenšení. Typické pro thumbnail workflow.',
            default => 'Bez ořezu. Obrázek se jen přizpůsobí velikosti výstupu.',
        };
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

    private function renderConversionItemLabel(array $state): Htmlable
    {
        $title = trim((string) ($state['label'] ?? ''));
        $fallbackTitle = trim((string) ($state['name'] ?? ''));
        $title = $title !== '' ? $title : ($fallbackTitle !== '' ? $fallbackTitle : 'Nová konverze');

        $segments = [
            '<span class="font-medium text-gray-950 dark:text-white">'.e($title).'</span>',
            $this->summaryPill($this->modeLabel($state['mode'] ?? null), $this->modePillClasses($state['mode'] ?? null)),
            '<span class="text-xs text-gray-500 dark:text-gray-400">'.e($this->dimensionsSummary($state)).'</span>',
        ];

        if (filled($ratio = $this->compactAspectRatio($state['aspect_ratio'] ?? null, $state['width'] ?? null, $state['height'] ?? null))) {
            $segments[] = $this->summaryPill($ratio, 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300');
        }

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['supports_focal_point'] ?? false)) {
            $segments[] = $this->summaryPill('FP', 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300');
        }

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['supports_manual_crop'] ?? false)) {
            $segments[] = $this->summaryPill('MC', 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300');
        }

        if ($this->usesCropMode($state['mode'] ?? null) && (bool) ($state['manual_crop_required'] ?? false)) {
            $segments[] = $this->summaryPill('Required', 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300');
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

        $lines = [
            '<div class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900/40">',
            '<div class="space-y-1">',
            '<div class="flex flex-wrap items-center gap-2">',
            '<span class="text-sm font-semibold text-gray-950 dark:text-white">'.e($title).'</span>',
            $this->summaryPill($this->modeLabel($state['mode'] ?? null), $this->modePillClasses($state['mode'] ?? null)),
            filled($state['group'] ?? null)
                ? $this->summaryPill($this->groupLabel($state['group']), 'bg-white text-gray-600 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700')
                : '',
            '</div>',
            '<p class="text-xs text-gray-500 dark:text-gray-400">'.e($this->dimensionsSummary($state)).' • '.e($this->compactAspectRatio($state['aspect_ratio'] ?? null, $state['width'] ?? null, $state['height'] ?? null) ?? 'poměr volný').'</p>',
            '</div>',
            '<div class="flex flex-wrap items-center gap-2">',
            $this->summaryPill((bool) ($state['show_in_editor'] ?? true) ? 'V editoru' : 'Skryto', (bool) ($state['show_in_editor'] ?? true)
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'),
            (bool) ($state['important'] ?? false)
                ? $this->summaryPill('Důležitá', 'bg-violet-50 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300')
                : '',
            '</div>',
            '</div>',
        ];

        return new HtmlString(implode('', array_filter($lines)));
    }

    private function summaryPill(string $label, string $classes): string
    {
        return '<span class="inline-flex items-center rounded-full px-2 py-1 text-[11px] font-medium '.$classes.'">'.e($label).'</span>';
    }

    private function modeLabel(mixed $mode): string
    {
        return self::MODE_OPTIONS[(string) $mode] ?? 'Bez ořezu';
    }

    private function groupLabel(mixed $group): string
    {
        return self::GROUP_OPTIONS[(string) $group] ?? (string) $group;
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
            'crop' => '<p class="text-sm text-gray-600 dark:text-gray-300">Tato konverze stojí hlavně na kompozici. Poměr stran je pevný a editor řeší, co ve výřezu zůstane.</p>',
            'crop_resize' => '<p class="text-sm text-gray-600 dark:text-gray-300">Typický workflow pro thumbnail. Nejprve se hlídá kompozice, teprve potom se dopočítá finální velikost.</p>',
            default => '<p class="text-sm text-gray-600 dark:text-gray-300">Resize režim drží jednoduchý výstup bez ořezu. Crop a focal point volby se zde záměrně nezobrazují.</p>',
        };
    }

    private function cropIntro(mixed $mode): string
    {
        return match ((string) $mode) {
            'crop_resize' => '<p class="text-sm text-gray-600 dark:text-gray-300">Thumbnail režim: kompozice je klíčová a cropper má být vždy svázaný s pevným poměrem stran této konverze.</p>',
            default => '<p class="text-sm text-gray-600 dark:text-gray-300">Tato konverze používá ořez podle pevného poměru stran. Níže nastavíte focal point, ruční crop a fallback strategii.</p>',
        };
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
