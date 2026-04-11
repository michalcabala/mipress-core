<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;

use function Filament\Support\generate_icon_html;

class EntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $collection = self::resolveCollection($schema);

        if (! $collection) {
            $record = $schema->getRecord();

            if ($record instanceof Entry && $record->collection_id) {
                $collection = $record->load('collection.blueprint')->collection;
            }
        }

        $blueprint = $collection?->blueprint;
        $hasSlug = (bool) ($collection?->slugs);
        $record = $schema->getRecord();
        $isEdit = $record instanceof Entry;

        $components = [];
        $seoSection = Section::make('SEO')
            ->icon('fal-magnifying-glass')
            ->collapsible()
            ->schema([
                TextInput::make('meta_title')
                    ->label('SEO titulek')
                    ->maxLength(60)
                    ->helperText('Doporučeno 50-60 znaků. Pokud zůstane prázdný, použije se titulek položky.'),
                Textarea::make('meta_description')
                    ->label('SEO popis')
                    ->maxLength(160)
                    ->rows(3)
                    ->helperText('Krátký popis pro výsledky vyhledávání a sdílení.'),
                CuratorPicker::make('og_image_id')
                    ->relationship('ogImage', 'id')
                    ->label('OG obrázek')
                    ->nullable()
                    ->helperText('Obrázek pro sdílení na sociálních sítích.'),
            ]);

        $components[] =
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Entry ? self::isReadOnlyForCurrentUser($record) : false)
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
                                            ->placeholder('Např. Novinka z redakce')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                if (($get('slug') ?? '') !== Str::slug($old)) {
                                                    return;
                                                }

                                                $set('slug', Str::slug($state));
                                            })
                                            ->columnSpan($hasSlug ? 1 : 2),

                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required($hasSlug)
                                            ->visible($hasSlug)
                                            ->maxLength(200)
                                            ->placeholder('novinka-z-redakce')
                                            ->helperText('Používá se v URL této položky.')
                                            ->rules(['alpha_dash']),
                                    ]),
                                ]),

                            Section::make('Obsah')
                                ->icon('fal-file-lines')
                                ->schema([
                                    Mason::make('data.content')
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
                                    ...BlueprintFieldResolver::resolveAll($blueprint->fields ?? []),
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
                                    DateTimePicker::make('published_at')
                                        ->label('Datum publikace')
                                        ->nullable()
                                        ->disabled(fn (): bool => ! self::canPublish($record))
                                        ->helperText('Pokud nastavíte budoucí datum a čas, obsah se uloží jako naplánovaný.'),
                                    Select::make('author_id')
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
                                        ->label('Nadřazená položka')
                                        ->options(fn (): array => self::getParentOptions($collection, $record))
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->visible(fn (): bool => self::supportsHierarchy($collection, $record))
                                        ->helperText('Použijte pro podstránky v hierarchických sekcích.'),
                                ]),

                            Section::make('Hlavní obrázek')
                                ->icon('fal-image')
                                ->schema([
                                    CuratorPicker::make('featured_image_id')
                                        ->relationship('featuredImage', 'id')
                                        ->label('')
                                        ->nullable(),
                                ]),

                            ...self::buildTaxonomySections($collection, $record),

                            Section::make('Stav')
                                ->visible($isEdit)
                                ->schema([
                                    TextEntry::make('status_badge')
                                        ->label('Stav publikace')
                                        ->state(fn (Entry $record): HtmlString => self::renderStatusBadge($record->status)),

                                    TextEntry::make('status_meta')
                                        ->label('Detail stavu')
                                        ->visible(fn (Entry $record): bool => self::renderStatusMeta($record) !== '')
                                        ->state(fn (Entry $record): HtmlString => new HtmlString(self::renderStatusMeta($record))),

                                    TextEntry::make('published_info')
                                        ->label('Datum publikace')
                                        ->state(fn (Entry $record): string => self::formatPublicationDate($record)),

                                    Actions::make([
                                        Action::make('moveToTrash')
                                            ->label('Přesunout do koše')
                                            ->icon('far-trash-can')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->action(function (EditRecord $livewire, Entry $record): void {
                                                $record->delete();
                                                Notification::make()->title('Položka přesunuta do koše')->success()->send();

                                                $livewire->redirect(EntryResource::getUrl('index', [
                                                    'collection' => $record->collection?->handle,
                                                ]));
                                            }),

                                        Action::make('deletePermanently')
                                            ->label('Smazat trvale')
                                            ->icon('far-trash-xmark')
                                            ->color('danger')
                                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                            ->requiresConfirmation()
                                            ->action(function (EditRecord $livewire, Entry $record): void {
                                                $collectionHandle = $record->collection?->handle;
                                                $record->forceDelete();
                                                Notification::make()->title('Položka byla trvale smazána')->success()->send();

                                                $livewire->redirect(EntryResource::getUrl('index', [
                                                    'collection' => $collectionHandle,
                                                ]));
                                            }),
                                    ])->fullWidth(),

                                    Actions::make([
                                        Action::make('duplicate')
                                            ->label('Duplikovat')
                                            ->icon('far-copy')
                                            ->color('info')
                                            ->action(function (EditRecord $livewire, Entry $record): void {
                                                $copy = $record->replicate();
                                                $copy->title = str($record->title)->append(' (kopie)')->toString();
                                                $copy->status = EntryStatus::Draft;
                                                $copy->slug = null;
                                                $copy->published_at = null;
                                                $copy->review_note = null;
                                                $copy->save();

                                                Notification::make()->title('Kopie vytvořena')->success()->send();
                                                $livewire->redirect(EntryResource::getUrl('edit', [
                                                    'record' => $copy,
                                                    'collection' => $copy->collection?->handle,
                                                ]));
                                            }),
                                    ])->fullWidth(),
                                ]),

                            Section::make('Detaily obsahu')
                                ->visible($isEdit)
                                ->schema([
                                    TextEntry::make('entry_id')
                                        ->label('ID')
                                        ->state(fn (Entry $record): string => (string) $record->id),
                                    TextEntry::make('created_info')
                                        ->label('Vytvořeno')
                                        ->state(fn (Entry $record): string => ($record->created_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('updated_info')
                                        ->label('Upraveno')
                                        ->state(fn (Entry $record): string => ($record->updated_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('published_info')
                                        ->label('Publikováno')
                                        ->state(fn (Entry $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),
                                ]),
                        ]),
                ]);

        if ($collection) {
            $components[] = Hidden::make('collection_id')
                ->default($collection->id);
        }

        return $schema->components($components);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }

    private static function resolveCollection(Schema $schema): ?Collection
    {
        $livewire = method_exists($schema, 'getLivewire') ? $schema->getLivewire() : null;

        if ($livewire !== null && property_exists($livewire, 'collectionHandle')) {
            $handle = $livewire->collectionHandle;

            if (filled($handle)) {
                return EntryResource::resolveCollectionByHandle((string) $handle);
            }
        }

        return EntryResource::getCurrentCollection();
    }

    private static function makePublicationStatusField(?Entry $record): ToggleButtons
    {
        return ToggleButtons::make('status')
            ->label('Stav publikování')
            ->options(self::getPublicationStatusOptions($record))
            ->colors(self::getPublicationStatusColors())
            ->icons(self::getPublicationStatusIcons())
            ->inline()
            ->required()
            ->default(EntryStatus::Draft->value)
            ->helperText(self::publicationStatusHelperText($record));
    }

    /**
     * @return array<string, string>
     */
    private static function getPublicationStatusOptions(?Entry $record): array
    {
        return collect(self::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    private static function getVisiblePublicationStatuses(?Entry $record): array
    {
        if (self::canPublish($record)) {
            return EntryStatus::cases();
        }

        if (! $record instanceof Entry) {
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

    private static function publicationStatusHelperText(?Entry $record): string
    {
        if (self::canPublish($record)) {
            return 'Budoucí datum a čas uloží obsah jako naplánovaný.';
        }

        if ($record instanceof Entry && in_array($record->status, [EntryStatus::Published, EntryStatus::Scheduled], true)) {
            return 'Po uložení budou změny odeslány ke schválení.';
        }

        return 'Vyberte, zda obsah uložit jako koncept nebo odeslat ke schválení.';
    }

    private static function canPublish(?Entry $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($record instanceof Entry) {
            return $user->can('publish', $record);
        }

        return $user->hasPermissionTo('entry.publish');
    }

    private static function supportsHierarchy(?Collection $collection, ?Entry $record): bool
    {
        if ($collection instanceof Collection) {
            return (bool) $collection->hierarchical;
        }

        if ($record instanceof Entry) {
            return (bool) $record->collection?->hierarchical;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function getParentOptions(?Collection $collection, ?Entry $record): array
    {
        $collectionId = $collection?->getKey() ?: $record?->collection_id;

        if (! $collectionId) {
            return [];
        }

        $query = Entry::query()
            ->where('collection_id', $collectionId)
            ->orderBy('title');

        if ($record instanceof Entry) {
            $query->whereKeyNot($record->getKey());
        }

        return $query
            ->get(['id', 'title'])
            ->pluck('title', 'id')
            ->all();
    }

    private static function formatPublicationDate(Entry $record): string
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

    private static function renderStatusMeta(Entry $record): string
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

    private static function isReadOnlyForCurrentUser(Entry $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && $record->status === EntryStatus::InReview;
    }

    /**
     * @return array<int, Section>
     */
    private static function buildTaxonomySections(?Collection $collection, ?Entry $record): array
    {
        $taxonomies = $collection?->taxonomies ?? collect();

        if ($taxonomies->isEmpty()) {
            return [];
        }

        $record?->loadMissing('terms');

        $fields = $taxonomies->map(function (Taxonomy $taxonomy) use ($record): Select|SelectTree {
            $taxonomyId = $taxonomy->getKey();

            if ($taxonomy->is_hierarchical) {
                return SelectTree::make("taxonomy__{$taxonomyId}")
                    ->label($taxonomy->title)
                    ->query(
                        fn () => Term::where('taxonomy_id', $taxonomyId)->ordered(),
                        'title',
                        'parent_id',
                    )
                    ->multiple()
                    ->enableBranchNode()
                    ->searchable()
                    ->parentNullValue(null)
                    ->afterStateHydrated(function ($component) use ($record, $taxonomyId): void {
                        if (! ($record instanceof Entry)) {
                            return;
                        }

                        if (filled($component->getState())) {
                            return;
                        }

                        $ids = $record->terms
                            ->where('taxonomy_id', $taxonomyId)
                            ->pluck('id')
                            ->all();
                        $component->state($ids);
                    })
                    ->dehydrated(false);
            }

            return Select::make("taxonomy__{$taxonomyId}")
                ->label($taxonomy->title)
                ->multiple()
                ->options(
                    Term::where('taxonomy_id', $taxonomyId)
                        ->ordered()
                        ->pluck('title', 'id')
                        ->toArray()
                )
                ->afterStateHydrated(function ($component) use ($record, $taxonomyId): void {
                    if (! ($record instanceof Entry)) {
                        return;
                    }

                    if (filled($component->getState())) {
                        return;
                    }

                    $ids = $record->terms
                        ->where('taxonomy_id', $taxonomyId)
                        ->pluck('id')
                        ->all();
                    $component->state($ids);
                })
                ->dehydrated(false)
                ->searchable();
        })->toArray();

        return [
            Section::make('Taxonomie')
                ->icon('fal-sitemap')
                ->collapsible()
                ->schema($fields),
        ];
    }
}
