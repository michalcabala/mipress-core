<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Schemas;

use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Support\EntryLikeFormBuilders;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;

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
        $seoSection = EntryLikeFormBuilders::makeSeoSection('položky', includeOgImage: true);

        $components[] = Grid::make([
            'default' => 1,
            'lg' => 4,
        ])->columnSpanFull()
            ->disabled(fn (): bool => $record instanceof Entry ? EntryLikeFormBuilders::isReadOnlyForCurrentUser($record) : false)
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
                            ->schema(EntryLikeFormBuilders::makePublicationFields(
                                $record,
                                [
                                    Select::make('parent_id')
                                        ->label('Nadřazená položka')
                                        ->options(fn (): array => self::getParentOptions($collection, $record))
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->visible(fn (): bool => self::supportsHierarchy($collection, $record))
                                        ->helperText('Použijte pro podstránky v hierarchických sekcích.'),
                                ],
                            )),

                        EntryLikeFormBuilders::makeFeaturedImageSection(),

                        ...self::buildTaxonomySections($collection, $record),

                        Section::make('Stav')
                            ->visible($isEdit)
                            ->schema([
                                ...EntryLikeFormBuilders::makeStatusOverviewEntries(),

                                Actions::make([
                                    Action::make('moveToTrash')
                                        ->label('Přesunout do koše')
                                        ->icon('far-trash-can')
                                        ->color('warning')
                                        ->requiresConfirmation()
                                        ->modalHeading(fn (Entry $record): string => 'Přesunout položku "'.$record->title.'" do koše?')
                                        ->modalDescription('Položka nebude trvale smazána a bude ji možné obnovit z koše.')
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $record->delete();
                                            Notification::make()
                                                ->title('Položka byla přesunuta do koše')
                                                ->body('Položka "'.$record->title.'" byla přesunuta do koše.')
                                                ->success()
                                                ->send();

                                            $livewire->redirect(EntryResource::getUrl('index', EntryResource::collectionUrlParameters($record->collection?->handle)));
                                        }),

                                    Action::make('deletePermanently')
                                        ->label('Smazat trvale')
                                        ->icon('far-trash-xmark')
                                        ->color('danger')
                                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                        ->requiresConfirmation()
                                        ->modalHeading(fn (Entry $record): string => 'Trvale smazat položku "'.$record->title.'"?')
                                        ->modalDescription('Tato akce položku nevratně odstraní ze systému včetně jejího aktuálního stavu.')
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $collectionHandle = EntryResource::normalizeCollectionHandle($record->collection?->handle);
                                            $recordTitle = $record->title;
                                            $record->forceDelete();
                                            Notification::make()
                                                ->title('Položka byla trvale smazána')
                                                ->body('Položka "'.$recordTitle.'" byla ze systému odstraněna natrvalo.')
                                                ->success()
                                                ->send();

                                            $livewire->redirect(EntryResource::getUrl('index', EntryResource::collectionUrlParameters($collectionHandle)));
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
                                            $copy->status = ContentStatus::Draft;
                                            $copy->slug = null;
                                            $copy->published_at = null;
                                            $copy->review_note = null;
                                            $copy->save();

                                            Notification::make()
                                                ->title('Kopie položky byla vytvořena')
                                                ->body('Nová položka "'.$copy->title.'" vznikla z položky "'.$record->title.'".')
                                                ->success()
                                                ->send();
                                            $livewire->redirect(EntryResource::getUrl('edit', [
                                                'record' => $copy,
                                                ...EntryResource::collectionUrlParameters($copy->collection?->handle),
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
