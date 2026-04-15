<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Tables;

use Awcodes\Curator\Components\Tables\CuratorColumn;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use MiPress\Core\FieldTypes\FieldTypeRegistry;
use MiPress\Core\Filament\Resources\Concerns\HasPublicationTableWorkflow;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Support\EntryLikeTableBuilders;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;

class EntriesTable
{
    use HasPublicationTableWorkflow;

    /**
     * @var array<int, array<int, string>>
     */
    private static array $taxonomyTermOptionsCache = [];

    public static function table(Table $table, ?Collection $collection = null): Table
    {
        $currentCollection = $collection ?? EntryResource::getCurrentCollection();

        return $table
            ->columns([
                CuratorColumn::make('featured_image_id')
                    ->label(__('mipress::admin.resources.page.table.columns.image'))
                    ->size(50),
                TextColumn::make('title')
                    ->label(__('mipress::admin.entry_like_form.fields.title'))
                    ->searchable()
                    ->sortable()
                    ->description(fn (Entry $record): ?string => filled($record->slug) ? '/'.$record->slug : null),
                EntryLikeTableBuilders::makeSlugColumn(),
                EntryLikeTableBuilders::makeStatusColumn(),
                ...static::getTaxonomyColumns($currentCollection),
                ...static::getBlueprintColumns($currentCollection),
                EntryLikeTableBuilders::makeUpdatedAtColumn(),
                EntryLikeTableBuilders::makeAuthorColumn(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                EntryLikeTableBuilders::makeStatusFilter(),
                EntryLikeTableBuilders::makeAuthorFilter(fn (): array => static::getAuthorFilterOptions($currentCollection)),
                EntryLikeTableBuilders::makeCreatedMonthFilter(fn (): array => static::getCreatedMonthOptions($currentCollection)),
                TrashedFilter::make(),
                ...static::getTaxonomyFilters($currentCollection),
                ...static::getBlueprintFilters($currentCollection),
            ])
            ->filtersFormSchema(fn (array $filters): array => static::getFiltersFormSchema($filters, $currentCollection))
            ->actions([
                ActionGroup::make([
                    static::makeViewLiveAction(),
                    static::makePreviewAction(),
                    static::makeTogglePublicationAction(),
                    EditAction::make()
                        ->visible(fn (Entry $record): bool => auth()->user()?->can('update', $record) === true && ! $record->trashed()),
                    static::refreshesPublicationStatusOverview(
                        RestoreAction::make()
                            ->visible(fn (Entry $record): bool => auth()->user()?->can('restore', $record) === true && $record->trashed())
                    ),
                    static::refreshesPublicationStatusOverview(
                        DeleteAction::make()
                            ->visible(fn (Entry $record): bool => auth()->user()?->can('delete', $record) === true && ! $record->trashed())
                    ),
                    static::refreshesPublicationStatusOverview(
                        ForceDeleteAction::make()
                            ->visible(fn (Entry $record): bool => auth()->user()?->can('forceDelete', $record) === true && $record->trashed())
                    ),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeBulkPublicationAction(),
                    static::refreshesPublicationStatusOverviewBulkAction(DeleteBulkAction::make()),
                    static::refreshesPublicationStatusOverviewBulkAction(RestoreBulkAction::make()),
                    static::refreshesPublicationStatusOverviewBulkAction(ForceDeleteBulkAction::make()),
                ]),
            ]);
    }

    // ── HasPublicationTableWorkflow configuration ──

    protected static function getContentModelClass(): string
    {
        return Entry::class;
    }

    protected static function getPreviewRouteName(): string
    {
        return 'preview.entry';
    }

    protected static function getPreviewRouteParameterName(): string
    {
        return 'entry';
    }

    protected static function getEditUrl(Model $record): string
    {
        return EntryResource::getUrl('edit', [
            'record' => $record,
            ...EntryResource::collectionUrlParameters($record->collection?->handle),
        ]);
    }

    protected static function getPublishPermission(): string
    {
        return 'entry.publish';
    }

    protected static function getContentLabel(): string
    {
        return __('mipress::admin.resources.entry.content_label');
    }

    protected static function getContentLabelPlural(): string
    {
        return __('mipress::admin.resources.entry.content_label_plural');
    }

    /**
     * @return array<int, string>
     */
    private static function getAuthorFilterOptions(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        return EntryLikeTableBuilders::getAuthorFilterOptions(
            Entry::query()
                ->when(
                    $collection,
                    fn (Builder $query): Builder => $query->where('collection_id', $collection->id),
                ),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function getCreatedMonthOptions(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        return EntryLikeTableBuilders::getCreatedMonthOptions(
            Entry::query()
                ->when(
                    $collection,
                    fn (Builder $query): Builder => $query->where('collection_id', $collection->id),
                ),
        );
    }

    /**
     * @return array<int, TextColumn>
     */
    private static function getTaxonomyColumns(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection) {
            return [];
        }

        $taxonomies = $collection->taxonomies
            ->filter(fn (Taxonomy $taxonomy): bool => (bool) ($taxonomy->show_in_entries_table ?? true))
            ->values();

        if ($taxonomies->isEmpty()) {
            return [];
        }

        return $taxonomies->map(function (Taxonomy $taxonomy): TextColumn {
            $taxonomyId = $taxonomy->getKey();

            return TextColumn::make("taxonomy_{$taxonomyId}")
                ->label($taxonomy->title)
                ->state(fn (Entry $record): string => static::formatTaxonomyTerms($record, $taxonomy))
                ->html()
                ->toggleable()
                ->searchable(
                    (bool) ($taxonomy->searchable_in_entries_table ?? false),
                    fn (Builder $query, string $search): Builder => static::applyTaxonomySearch($query, $taxonomyId, $search),
                )
                ->sortable(
                    (bool) ($taxonomy->sortable_in_entries_table ?? false),
                    fn (Builder $query, string $direction): Builder => static::applyTaxonomySort($query, $taxonomyId, $direction),
                );
        })->toArray();
    }

    private static function applyTaxonomySearch(Builder $query, int $taxonomyId, string $search): Builder
    {
        if (trim($search) === '') {
            return $query;
        }

        return $query->whereHas(
            'terms',
            fn (Builder $termsQuery): Builder => $termsQuery
                ->where('terms.taxonomy_id', $taxonomyId)
                ->where('terms.title', 'like', '%'.$search.'%'),
        );
    }

    private static function applyTaxonomySort(Builder $query, int $taxonomyId, string $direction): Builder
    {
        $direction = mb_strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $entryTable = $query->getModel()->getTable();

        $sortSubQuery = DB::table('entry_term')
            ->join('terms', 'terms.id', '=', 'entry_term.term_id')
            ->selectRaw('MIN(terms.title)')
            ->whereColumn('entry_term.entry_id', $entryTable.'.id')
            ->where('terms.taxonomy_id', $taxonomyId);

        return $query->orderBy($sortSubQuery, $direction);
    }

    private static function formatTaxonomyTerms(Entry $record, Taxonomy $taxonomy): string
    {
        $terms = $record->terms
            ->where('taxonomy_id', $taxonomy->getKey())
            ->pluck('title')
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return '<span class="text-gray-500">-</span>';
        }

        if (static::getTaxonomyDisplayMode($taxonomy) === 'text') {
            return e($terms->implode(', '));
        }

        $badgeClasses = implode(' ', static::getTaxonomyBadgeClasses($taxonomy));

        return $terms
            ->map(fn (string $term): string => '<span class="'.e($badgeClasses).'">'.e($term).'</span>')
            ->implode(' ');
    }

    private static function getTaxonomyDisplayMode(Taxonomy $taxonomy): string
    {
        $displayMode = (string) ($taxonomy->entries_table_display_mode ?? 'badges');

        return in_array($displayMode, ['badges', 'text'], true)
            ? $displayMode
            : 'badges';
    }

    /**
     * @return array<int, string>
     */
    private static function getTaxonomyBadgeClasses(Taxonomy $taxonomy): array
    {
        $baseClasses = ['fi-badge', 'fi-color-custom'];
        $palette = (string) ($taxonomy->entries_table_badge_palette ?? 'neutral');

        return array_merge($baseClasses, match ($palette) {
            'primary' => ['bg-blue-100', 'text-blue-700', 'dark:bg-blue-900/40', 'dark:text-blue-200'],
            'success' => ['bg-emerald-100', 'text-emerald-700', 'dark:bg-emerald-900/40', 'dark:text-emerald-200'],
            'warning' => ['bg-amber-100', 'text-amber-700', 'dark:bg-amber-900/40', 'dark:text-amber-200'],
            'danger' => ['bg-red-100', 'text-red-700', 'dark:bg-red-900/40', 'dark:text-red-200'],
            'info' => ['bg-cyan-100', 'text-cyan-700', 'dark:bg-cyan-900/40', 'dark:text-cyan-200'],
            default => ['bg-gray-100', 'text-gray-700', 'dark:bg-gray-800', 'dark:text-gray-200'],
        });
    }

    /**
     * @return array<int, mixed>
     */
    private static function getBlueprintColumns(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection?->blueprint) {
            return [];
        }

        return BlueprintFieldResolver::resolveTableColumns($collection->blueprint->fields ?? []);
    }

    /**
     * @return array<int, mixed>
     */
    private static function getBlueprintFilters(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection?->blueprint) {
            return [];
        }

        return BlueprintFieldResolver::resolveFilters($collection->blueprint->fields ?? []);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, Section>
     */
    private static function getFiltersFormSchema(array $filters, ?Collection $collection = null): array
    {
        $sections = [];

        $taxonomyFilters = static::getTaxonomyFilterComponents($filters, $collection);
        $blueprintFilters = static::getBlueprintFilterComponents($filters, $collection);

        return EntryLikeTableBuilders::buildBaseFiltersFormSchema($filters, [
            $taxonomyFilters !== []
                ? Section::make(__('mipress::admin.filters.taxonomy'))->schema($taxonomyFilters)
                : null,
            $blueprintFilters !== []
                ? Section::make(__('mipress::admin.filters.blueprint_fields'))->schema($blueprintFilters)
                : null,
        ]);
    }

    /**
     * @return array<int, Filter>
     */
    private static function getTaxonomyFilters(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection) {
            return [];
        }

        $taxonomies = $collection->taxonomies
            ->filter(fn (Taxonomy $taxonomy): bool => (bool) ($taxonomy->show_in_entries_filter ?? true))
            ->values();

        if ($taxonomies->isEmpty()) {
            return [];
        }

        return $taxonomies->map(function (Taxonomy $taxonomy): Filter {
            $taxonomyId = $taxonomy->getKey();

            if ($taxonomy->is_hierarchical) {
                return Filter::make("taxonomy_{$taxonomyId}")
                    ->label($taxonomy->title)
                    ->schema([
                        SelectTree::make("term_ids_{$taxonomyId}")
                            ->label($taxonomy->title)
                            ->query(
                                fn () => Term::where('taxonomy_id', $taxonomyId)->ordered(),
                                'title',
                                'parent_id',
                            )
                            ->multiple()
                            ->enableBranchNode()
                            ->searchable()
                            ->parentNullValue(null),
                    ])
                    ->query(function (Builder $query, array $data) use ($taxonomyId): Builder {
                        $termIds = $data["term_ids_{$taxonomyId}"] ?? [];

                        if (empty($termIds)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'terms',
                            fn (Builder $q): Builder => $q->whereIn('terms.id', $termIds)
                                ->where('taxonomy_id', $taxonomyId),
                        );
                    })
                    ->indicateUsing(function (array $data) use ($taxonomy, $taxonomyId): ?string {
                        $termIds = $data["term_ids_{$taxonomyId}"] ?? [];

                        if (empty($termIds)) {
                            return null;
                        }

                        $names = Term::whereIn('id', $termIds)->pluck('title')->implode(', ');

                        return $taxonomy->title.': '.$names;
                    });
            }

            return Filter::make("taxonomy_{$taxonomyId}")
                ->label($taxonomy->title)
                ->schema([
                    Select::make("term_ids_{$taxonomyId}")
                        ->label($taxonomy->title)
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => static::getTaxonomyTermOptions($taxonomyId)),
                ])
                ->query(function (Builder $query, array $data) use ($taxonomyId): Builder {
                    $termIds = $data["term_ids_{$taxonomyId}"] ?? [];

                    if (empty($termIds)) {
                        return $query;
                    }

                    return $query->whereHas(
                        'terms',
                        fn (Builder $q): Builder => $q->whereIn('terms.id', $termIds)
                            ->where('taxonomy_id', $taxonomyId),
                    );
                })
                ->indicateUsing(function (array $data) use ($taxonomy, $taxonomyId): ?string {
                    $termIds = $data["term_ids_{$taxonomyId}"] ?? [];

                    if (empty($termIds)) {
                        return null;
                    }

                    $names = Term::whereIn('id', $termIds)->pluck('title')->implode(', ');

                    return $taxonomy->title.': '.$names;
                });
        })->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    private static function getTaxonomyFilterComponents(array $filters, ?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection) {
            return [];
        }

        return $collection->taxonomies
            ->filter(fn (Taxonomy $taxonomy): bool => (bool) ($taxonomy->show_in_entries_filter ?? true))
            ->map(fn (Taxonomy $taxonomy): mixed => $filters["taxonomy_{$taxonomy->getKey()}"] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, mixed>
     */
    private static function getBlueprintFilterComponents(array $filters, ?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        if (! $collection?->blueprint) {
            return [];
        }

        $registry = app(FieldTypeRegistry::class);
        $blueprintFields = static::flattenBlueprintFields($collection->blueprint->fields ?? []);

        return collect($blueprintFields)
            ->map(function (array $fieldDef) use ($filters, $registry): mixed {
                $handle = $fieldDef['handle'] ?? null;
                $typeKey = $fieldDef['type'] ?? 'text';

                if (! $handle || ! $registry->has($typeKey)) {
                    return null;
                }

                $type = $registry->get($typeKey);

                if ($type->toFilter($handle, $fieldDef['label'] ?? $handle, $fieldDef['config'] ?? []) === null) {
                    return null;
                }

                return $filters[$handle] ?? null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function flattenBlueprintFields(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $firstItem = $fields[0] ?? [];

        if (isset($firstItem['section'])) {
            return collect($fields)
                ->flatMap(fn (array $section): array => $section['fields'] ?? [])
                ->values()
                ->all();
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    private static function getTaxonomyTermOptions(int $taxonomyId): array
    {
        if (array_key_exists($taxonomyId, static::$taxonomyTermOptionsCache)) {
            return static::$taxonomyTermOptionsCache[$taxonomyId];
        }

        static::$taxonomyTermOptionsCache[$taxonomyId] = Term::where('taxonomy_id', $taxonomyId)
            ->ordered()
            ->pluck('title', 'id')
            ->toArray();

        return static::$taxonomyTermOptionsCache[$taxonomyId];
    }
}
