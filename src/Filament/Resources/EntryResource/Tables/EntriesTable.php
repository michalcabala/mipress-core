<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Tables;

use App\Models\User;
use Awcodes\Curator\Components\Tables\CuratorColumn;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\FieldTypes\FieldTypeRegistry;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\WorkflowTransitionService;

class EntriesTable
{
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
                    ->label('Obrázek')
                    ->size(50),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Entry $record): ?string => filled($record->slug) ? '/'.$record->slug : null),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->default('—'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->icon(fn (EntryStatus $state): ?string => $state->getIcon())
                    ->color(fn (EntryStatus $state) => $state->getColor())
                    ->sortable(),
                ...static::getTaxonomyColumns($currentCollection),
                ...static::getBlueprintColumns($currentCollection),
                TextColumn::make('updated_at')
                    ->label('Datum')
                    ->isoDateTime('LLL')
                    ->description(fn ($record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                        ? 'Vytvořeno '.$record->created_at->isoFormat('LLL')
                        : null)
                    ->sortable()
                    ->toggleable(),
                UserColumn::make('author.name')
                    ->label('Autor')
                    ->state(fn (Entry $record): ?User => $record->author)
                    ->sortable()
                    ->toggleable()
                    ->wrapped(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(EntryStatus::class),
                UserSelectFilter::make('author_id')
                    ->label('Autor')
                    ->options(fn (): array => static::getAuthorFilterOptions($currentCollection))
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('created_month')
                    ->label('Měsíc')
                    ->options(fn (): array => static::getCreatedMonthOptions($currentCollection))
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
                            return $query;
                        }

                        [$year, $month] = explode('-', $value);

                        return $query
                            ->whereYear('created_at', (int) $year)
                            ->whereMonth('created_at', (int) $month);
                    }),
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
                    RestoreAction::make()
                        ->visible(fn (Entry $record): bool => auth()->user()?->can('restore', $record) === true && $record->trashed()),
                    DeleteAction::make()
                        ->visible(fn (Entry $record): bool => auth()->user()?->can('delete', $record) === true && ! $record->trashed()),
                    ForceDeleteAction::make()
                        ->visible(fn (Entry $record): bool => auth()->user()?->can('forceDelete', $record) === true && $record->trashed()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeBulkPublicationAction(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function makeViewLiveAction(): Action
    {
        return Action::make('viewLive')
            ->label('Zobrazit na webu')
            ->icon('far-arrow-up-right-from-square')
            ->color('gray')
            ->url(fn (Entry $record): ?string => $record->getPublicUrl(), shouldOpenInNewTab: true)
            ->visible(fn (Entry $record): bool => auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status === EntryStatus::Published
                && filled($record->getPublicUrl()));
    }

    private static function makePreviewAction(): Action
    {
        return Action::make('preview')
            ->label('Náhled')
            ->icon('far-eye')
            ->color('gray')
            ->url(
                fn (Entry $record): string => URL::temporarySignedRoute(
                    'preview.entry',
                    now()->addHour(),
                    ['entry' => $record->getKey()],
                ),
                shouldOpenInNewTab: true,
            )
            ->visible(fn (Entry $record): bool => auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status !== EntryStatus::Published);
    }

    private static function makeTogglePublicationAction(): Action
    {
        return Action::make('togglePublicationStatus')
            ->label(fn (Entry $record): string => match ($record->status) {
                EntryStatus::Published => 'Zrušit publikaci',
                EntryStatus::Scheduled => 'Zrušit plánování',
                default => 'Publikovat',
            })
            ->icon(fn (Entry $record): string => match ($record->status) {
                EntryStatus::Published => 'far-circle-minus',
                EntryStatus::Scheduled => EntryStatus::Draft->getIcon(),
                default => EntryStatus::Published->getIcon(),
            })
            ->color(fn (Entry $record): string|array|null => match ($record->status) {
                EntryStatus::Published, EntryStatus::Scheduled => EntryStatus::Draft->getColor(),
                default => EntryStatus::Published->getColor(),
            })
            ->requiresConfirmation()
            ->visible(fn (Entry $record): bool => auth()->user()?->can('publish', $record) === true && ! $record->trashed())
            ->action(function (Entry $record): void {
                $currentStatus = $record->status;

                static::transitionPublicationRecord($record, $currentStatus, 'toggle');

                Notification::make()
                    ->title(match ($currentStatus) {
                        EntryStatus::Published => 'Publikace zrušena',
                        EntryStatus::Scheduled => 'Plánování zrušeno',
                        default => 'Položka publikována',
                    })
                    ->success()
                    ->send();
            });
    }

    private static function makeBulkPublicationAction(): BulkAction
    {
        return BulkAction::make('changePublicationStatus')
            ->label('Změnit publikaci')
            ->icon('far-arrows-rotate')
            ->visible(fn (): bool => auth()->user()?->hasPermissionTo('entry.publish') === true)
            ->schema([
                Select::make('target_status')
                    ->label('Cílový stav')
                    ->options([
                        'published' => 'Publikovat',
                        'draft' => 'Zrušit publikaci',
                    ])
                    ->native(false)
                    ->required(),
            ])
            ->action(function (EloquentCollection $records, array $data): void {
                $targetStatus = $data['target_status'] ?? null;
                $updated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof Entry || auth()->user()?->can('publish', $record) !== true) {
                        $skipped++;

                        continue;
                    }

                    $statusChanged = static::transitionPublicationRecord($record, $record->status, (string) $targetStatus);

                    if ($statusChanged) {
                        $updated++;
                    }
                }

                Notification::make()
                    ->title($updated > 0 ? 'Stav publikace změněn' : 'Bez změny')
                    ->body("Aktualizováno {$updated} položek, přeskočeno {$skipped}.")
                    ->{$updated > 0 ? 'success' : 'warning'}()
                    ->send();
            });
    }

    private static function transitionPublicationRecord(Entry $record, EntryStatus $currentStatus, string $mode): bool
    {
        $workflowTransitions = app(WorkflowTransitionService::class);

        if ($mode === 'published') {
            if ($currentStatus === EntryStatus::Published) {
                return false;
            }

            if ($currentStatus === EntryStatus::Scheduled) {
                $workflowTransitions->publishNow($record);

                return true;
            }

            $workflowTransitions->publish($record);

            return true;
        }

        if ($mode === 'draft') {
            if ($currentStatus === EntryStatus::Draft) {
                return false;
            }

            if ($currentStatus === EntryStatus::Scheduled) {
                $workflowTransitions->cancelSchedule($record);

                return true;
            }

            $workflowTransitions->unpublish($record);

            return true;
        }

        if ($currentStatus === EntryStatus::Published) {
            $workflowTransitions->unpublish($record);

            return true;
        }

        if ($currentStatus === EntryStatus::Scheduled) {
            $workflowTransitions->cancelSchedule($record);

            return true;
        }

        $workflowTransitions->publish($record);

        return true;
    }

    /**
     * @return array<int, string>
     */
    private static function getAuthorFilterOptions(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        $authorIds = Entry::query()
            ->when(
                $collection,
                fn (Builder $query): Builder => $query->where('collection_id', $collection->id),
            )
            ->whereNotNull('author_id')
            ->distinct()
            ->pluck('author_id')
            ->filter();

        if ($authorIds->isEmpty()) {
            return [];
        }

        $authors = User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->get();

        return UserFieldRenderer::mapUsersToOptionLabels($authors);
    }

    /**
     * @return array<string, string>
     */
    private static function getCreatedMonthOptions(?Collection $collection = null): array
    {
        $collection ??= EntryResource::getCurrentCollection();

        $createdMonthExpression = static::getCreatedMonthExpression(Entry::query()->getModel()->getConnection()->getDriverName());

        $values = Entry::query()
            ->when(
                $collection,
                fn (Builder $query): Builder => $query->where('collection_id', $collection->id),
            )
            ->whereNotNull('created_at')
            ->toBase()
            ->selectRaw("{$createdMonthExpression} as created_month")
            ->distinct()
            ->orderByDesc('created_month')
            ->pluck('created_month')
            ->filter(fn (?string $value): bool => filled($value))
            ->values();

        $options = [];

        foreach ($values as $value) {
            try {
                $date = Carbon::createFromFormat('Y-m', $value);
                $date->locale('cs_CZ');
                $options[$value] = (string) str($date->translatedFormat('F Y'))->ucfirst();
            } catch (\Throwable) {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    private static function getCreatedMonthExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
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

        $publicationFilters = array_values(array_filter([
            $filters['status'] ?? null,
            $filters['trashed'] ?? null,
        ]));

        if ($publicationFilters !== []) {
            $sections[] = Section::make('Publikace')
                ->schema($publicationFilters);
        }

        $metadataFilters = array_values(array_filter([
            $filters['author_id'] ?? null,
            $filters['created_month'] ?? null,
        ]));

        if ($metadataFilters !== []) {
            $sections[] = Section::make('Metadata')
                ->schema($metadataFilters);
        }

        $taxonomyFilters = static::getTaxonomyFilterComponents($filters, $collection);

        if ($taxonomyFilters !== []) {
            $sections[] = Section::make('Taxonomie')
                ->schema($taxonomyFilters);
        }

        $blueprintFilters = static::getBlueprintFilterComponents($filters, $collection);

        if ($blueprintFilters !== []) {
            $sections[] = Section::make('Pole šablony')
                ->schema($blueprintFilters);
        }

        return $sections;
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
