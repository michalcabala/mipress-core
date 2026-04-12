<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use MiPress\Core\Enums\EntryStatus;
use Awcodes\Curator\Components\Forms\CuratorPicker;
use MiPress\Core\Filament\Forms\Components\UserSelect;
use MiPress\Core\Filament\Resources\Concerns\HasReactivePublicationFields;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Page;

use function Filament\Support\generate_icon_html;

class PageForm
{
    use HasReactivePublicationFields;

    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $isEdit = $record instanceof Page;

        $components = [];
        $seoSection = Section::make('SEO')
            ->icon('fal-magnifying-glass')
            ->collapsible()
            ->schema([
                TextInput::make('meta_title')
                    ->label('SEO titulek')
                    ->maxLength(60)
                    ->helperText('Doporučeno 50-60 znaků. Pokud zůstane prázdný, použije se titulek stránky.'),
                Textarea::make('meta_description')
                    ->label('SEO popis')
                    ->maxLength(160)
                    ->rows(3)
                    ->helperText('Krátký popis pro výsledky vyhledávání a sdílení.'),
            ]);

        $components[] =
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Page ? self::isReadOnlyForCurrentUser($record) : false)
                ->schema([
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 3])
                        ->schema([
                            Section::make('Základ')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('title')
                                            ->label('Titulek')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Např. O nás')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                if (($get('slug') ?? '') !== Str::slug($old)) {
                                                    return;
                                                }

                                                $set('slug', Str::slug($state));
                                            }),

                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(200)
                                            ->placeholder('o-nas')
                                            ->helperText('Používá se v URL stránky.')
                                            ->rules(['alpha_dash']),
                                    ]),
                                ]),

                            Section::make('Obsah')
                                ->icon('fal-file-lines')
                                ->schema([
                                    Mason::make('content')
                                        ->label('Obsah')
                                        ->bricks(EditorialBrickCollection::make())
                                        ->previewLayout('layouts.mason-preview')
                                        ->colorModeToggle()
                                        ->defaultColorMode('light')
                                        ->doubleClickToEdit()
                                        ->displayActionsAsGrid()
                                        ->sortBricks()
                                        ->sidebarPosition(SidebarPosition::End)
                                        ->extraInputAttributes(['style' => 'min-height: 42rem;'])
                                        ->columnSpanFull(),
                                ]),
                            ...($isEdit ? [] : [$seoSection]),
                        ]),

                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 1])
                        ->schema([
                            Section::make('Publikace')
                                ->icon('fal-calendar')
                                ->schema([
                                    self::makePublicationStatusField($record),
                                    self::makePublicationDateField($record),
                                    UserSelect::make('author_id')
                                        ->label('Autor')
                                        ->relationship('author', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->required()
                                        ->default(fn () => auth()->id()),
                                    TextInput::make('sort_order')
                                        ->label('Pořadí')
                                        ->numeric()
                                        ->default(0),
                                    Select::make('parent_id')
                                        ->label('Nadřazená stránka')
                                        ->options(fn (): array => self::getParentOptions($record))
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Vyberte nadřazenou stránku pro vytvoření hierarchie.'),
                                ]),

                            Section::make('Hlavní obrázek')
                                ->icon('fal-image')
                                ->schema([
                                    CuratorPicker::make('featured_image_id')
                                        ->label(''),
                                ]),

                            Section::make('Stav')
                                ->visible($isEdit)
                                ->schema([
                                    TextEntry::make('status_badge')
                                        ->label('Stav publikace')
                                        ->state(fn (Page $record): HtmlString => self::renderStatusBadge($record->status)),

                                    TextEntry::make('status_meta')
                                        ->label('Detail stavu')
                                        ->visible(fn (Page $record): bool => self::renderStatusMeta($record) !== '')
                                        ->state(fn (Page $record): HtmlString => new HtmlString(self::renderStatusMeta($record))),

                                    TextEntry::make('published_status_at')
                                        ->label('Datum publikace')
                                        ->state(fn (Page $record): string => self::formatPublicationDate($record)),

                                    Actions::make([
                                        Action::make('moveToTrash')
                                            ->label('Přesunout do koše')
                                            ->icon('far-trash-can')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->modalHeading(fn (Page $record): string => 'Přesunout stránku "'.$record->title.'" do koše?')
                                            ->modalDescription('Stránka nebude trvale smazána a bude ji možné obnovit z koše.')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $record->delete();
                                                Notification::make()
                                                    ->title('Stránka byla přesunuta do koše')
                                                    ->body('Stránka "'.$record->title.'" byla přesunuta do koše.')
                                                    ->success()
                                                    ->send();

                                                $livewire->redirect(PageResource::getUrl('index'));
                                            }),

                                        Action::make('deletePermanently')
                                            ->label('Smazat trvale')
                                            ->icon('far-trash-xmark')
                                            ->color('danger')
                                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                            ->requiresConfirmation()
                                            ->modalHeading(fn (Page $record): string => 'Trvale smazat stránku "'.$record->title.'"?')
                                            ->modalDescription('Tato akce stránku nevratně odstraní ze systému včetně jejího aktuálního stavu.')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $recordTitle = $record->title;
                                                $record->forceDelete();
                                                Notification::make()
                                                    ->title('Stránka byla trvale smazána')
                                                    ->body('Stránka "'.$recordTitle.'" byla ze systému odstraněna natrvalo.')
                                                    ->success()
                                                    ->send();

                                                $livewire->redirect(PageResource::getUrl('index'));
                                            }),
                                    ])->fullWidth(),

                                    Actions::make([
                                        Action::make('duplicate')
                                            ->label('Duplikovat')
                                            ->icon('far-copy')
                                            ->color('info')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $copy = $record->replicate();
                                                $copy->title = str($record->title)->append(' (kopie)')->toString();
                                                $copy->status = EntryStatus::Draft;
                                                $copy->slug = null;
                                                $copy->published_at = null;
                                                $copy->review_note = null;
                                                $copy->save();

                                                Notification::make()
                                                    ->title('Kopie stránky byla vytvořena')
                                                    ->body('Nová stránka "'.$copy->title.'" vznikla ze stránky "'.$record->title.'".')
                                                    ->success()
                                                    ->send();
                                                $livewire->redirect(PageResource::getUrl('edit', ['record' => $copy]));
                                            }),
                                    ])->fullWidth(),
                                ]),

                            Section::make('Detaily stránky')
                                ->visible($isEdit)
                                ->schema([
                                    TextEntry::make('page_id')
                                        ->label('ID')
                                        ->state(fn (Page $record): string => (string) $record->id),
                                    TextEntry::make('created_info')
                                        ->label('Vytvořeno')
                                        ->state(fn (Page $record): string => ($record->created_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('updated_info')
                                        ->label('Upraveno')
                                        ->state(fn (Page $record): string => ($record->updated_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('published_info')
                                        ->label('Publikováno')
                                        ->state(fn (Page $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),
                                ]),
                        ]),
                ]);

        return $schema->components($components);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }

    private static function makePublicationStatusField(?Page $record): ToggleButtons
    {
        return self::configureReactivePublicationStatusField(
            ToggleButtons::make('status')
                ->label('Stav publikování')
                ->options(self::getPublicationStatusOptions($record))
                ->colors(self::getPublicationStatusColors())
                ->icons(self::getPublicationStatusIcons())
                ->inline()
                ->required()
                ->default(EntryStatus::Draft->value)
                ->helperText(self::publicationStatusHelperText($record)),
            self::canPublish($record),
        );
    }

    private static function makePublicationDateField(?Page $record): DateTimePicker
    {
        return self::configureReactivePublicationDateField(
            DateTimePicker::make('published_at')
                ->label('Datum publikace')
                ->nullable()
                ->disabled(fn (): bool => ! self::canPublish($record))
                ->helperText('Pokud nastavíte budoucí datum a čas, obsah se uloží jako naplánovaný.'),
            self::canPublish($record),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function getPublicationStatusOptions(?Page $record): array
    {
        return collect(self::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    private static function getVisiblePublicationStatuses(?Page $record): array
    {
        if (self::canPublish($record)) {
            return EntryStatus::cases();
        }

        if (! $record instanceof Page) {
            return [EntryStatus::Draft, EntryStatus::InReview];
        }

        return match ($record->status) {
            EntryStatus::Published, EntryStatus::Scheduled => [$record->status, EntryStatus::InReview],
            EntryStatus::Rejected => [$record->status, EntryStatus::Draft, EntryStatus::InReview],
            default => [EntryStatus::Draft, EntryStatus::InReview],
        };
    }

    /**
     * @return array<string, string|array|null>
     */
    private static function getPublicationStatusColors(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getColor()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private static function getPublicationStatusIcons(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getIcon()])
            ->all();
    }

    private static function publicationStatusHelperText(?Page $record): string
    {
        if (self::canPublish($record)) {
            return 'Budoucí datum a čas uloží obsah jako naplánovaný.';
        }

        if ($record instanceof Page && in_array($record->status, [EntryStatus::Published, EntryStatus::Scheduled], true)) {
            return 'Po uložení budou změny odeslány ke schválení.';
        }

        return 'Vyberte, zda obsah uložit jako koncept nebo odeslat ke schválení.';
    }

    private static function canPublish(?Page $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($record instanceof Page) {
            return $user->can('publish', $record);
        }

        return $user->hasPermissionTo('entry.publish');
    }

    /**
     * @return array<int, string>
     */
    private static function getParentOptions(?Page $record): array
    {
        $locale = $record?->locale;

        $query = Page::query()
            ->orderBy('title')
            ->when(
                filled($locale),
                fn (Builder $builder): Builder => $builder->where('locale', $locale),
            );

        if ($record instanceof Page) {
            $query->whereKeyNot($record->getKey());
        }

        $pages = $query
            ->get(['id', 'title', 'parent_id'])
            ->groupBy(fn (Page $page): string => (string) ($page->parent_id ?? 0));

        return self::flattenParentOptions($pages);
    }

    /**
     * @param  Collection<string, Collection<int, Page>>  $groupedPages
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private static function flattenParentOptions(
        Collection $groupedPages,
        int $parentId = 0,
        int $depth = 0,
        array $options = [],
    ): array {
        /** @var Collection<int, Page> $children */
        $children = $groupedPages->get((string) $parentId, collect());

        foreach ($children as $child) {
            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
            $options[(int) $child->getKey()] = $prefix.$child->title;
            $options = self::flattenParentOptions($groupedPages, (int) $child->getKey(), $depth + 1, $options);
        }

        return $options;
    }

    private static function formatPublicationDate(Page $record): string
    {
        $publicationAt = $record->scheduled_at ?? $record->published_at;

        return $publicationAt?->format('j. n. Y H:i') ?? '—';
    }

    private static function renderStatusBadge(EntryStatus $status): HtmlString
    {
        $color = $status->getColor();
        $color = is_string($color) ? $color : 'gray';
        $icon = generate_icon_html(
            $status->getIcon(),
            attributes: new ComponentAttributeBag(['class' => 'shrink-0']),
            size: IconSize::Small,
        )?->toHtml() ?? '';

        return new HtmlString(
            '<span class="fi-badge fi-color-'.e($color).' fi-size-sm">'
            .'<span class="inline-flex items-center gap-1.5">'.$icon.'<span>'.e($status->getLabel()).'</span></span>'
            .'</span>'
        );
    }

    private static function renderStatusMeta(Page $record): string
    {
        $statusLog = AuditLog::query()
            ->with('user')
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'status_changed')
            ->where('new_values->status', $record->status->value)
            ->latest('created_at')
            ->first();

        $actor = e($statusLog?->user?->name ?? 'Systém');
        $date = e($statusLog?->created_at?->format('j. n. Y H:i') ?? '—');
        $scheduledAt = $record->scheduled_at ?? $record->published_at;

        return match ($record->status) {
            EntryStatus::Published => 'Publikováno · schválil '.$actor.' · '.$date,
            EntryStatus::Rejected => 'Zamítnuto · zamítl '.$actor.' · '.$date.'<br><strong>Důvod:</strong> '.e($record->review_note ?? '—'),
            EntryStatus::Scheduled => 'Naplánováno na '.e($scheduledAt?->format('j. n. Y H:i') ?? '—').' · naplánoval '.$actor,
            EntryStatus::InReview => 'Odesláno ke schválení · odeslal '.$actor.' · '.$date,
            default => '',
        };
    }

    private static function isReadOnlyForCurrentUser(Page $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && $record->status === EntryStatus::InReview;
    }
}
