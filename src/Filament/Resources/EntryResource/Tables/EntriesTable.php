<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Tables;

use App\Models\User;
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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;

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
                ImageColumn::make('featuredImage')
                    ->label('Obrázek')
                    ->height(40)
                    ->width(40)
                    ->checkFileExistence(false)
                    ->state(fn (Entry $record): ?string => mipress_media_url($record->featuredImage, 'thumbnail')),
                IconColumn::make('resource_lock_state')
                    ->label('Zámek')
                    ->alignCenter()
                    ->state(fn (Entry $record): ?string => static::getResourceLockState($record))
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'mine', 'other' => 'fal-lock',
                        default => null,
                    })
                    ->color(fn (?string $state): ?string => match ($state) {
                        'mine' => 'primary',
                        'other' => 'danger',
                        default => null,
                    })
                    ->tooltip(fn (Entry $record, ?string $state): ?string => static::getResourceLockTooltip($record, $state)),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('author_id')
                    ->label('Autor')
                    ->options(fn (): array => static::getAuthorFilterOptions($currentCollection))
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
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(EntryStatus::class)
                    ->native(false),
                ...static::getTaxonomyFilters($currentCollection),
                ...static::getBlueprintFilters($currentCollection),
                TrashedFilter::make(),
            ])
            ->filtersFormSchema(fn (array $filters): array => static::getFiltersFormSchema($filters, $currentCollection))
            ->actions([
                ActionGroup::make([
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
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function getResourceLockState(Entry $record): ?string
    {
        $resourceLock = $record->resourceLock;

        if ($resourceLock === null || $resourceLock->isExpired($record->getLockTimeout())) {
            return null;
        }

        return $record->isLockedByCurrentUser() ? 'mine' : 'other';
    }

    private static function getResourceLockTooltip(Entry $record, ?string $state): ?string
    {
        return match ($state) {
            'mine' => 'Právě upravujete vy',
            'other' => filled($record->resourceLock?->user?->name)
                ? 'Právě upravuje '.$record->resourceLock->user->name
                : 'Právě upravuje jiný uživatel',
            default => null,
        };
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

        return User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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

        $taxonomies = $collection->taxonomies;

        if ($taxonomies->isEmpty()) {
            return [];
        }

        return $taxonomies->map(fn (Taxonomy $taxonomy): TextColumn => TextColumn::make("taxonomy_{$taxonomy->getKey()}")
            ->label($taxonomy->title)
            ->state(fn (Entry $record): string => static::formatTaxonomyBadges($record, $taxonomy))
            ->html()
            ->toggleable()
            ->searchable(false)
            ->sortable(false)
        )->toArray();
    }

    private static function formatTaxonomyBadges(Entry $record, Taxonomy $taxonomy): string
    {
        $terms = $record->terms
            ->where('taxonomy_id', $taxonomy->getKey())
            ->pluck('title')
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return '<span class="text-gray-500">-</span>';
        }

        return $terms
            ->map(fn (string $term): string => '<span class="fi-badge fi-color-custom bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200">'.e($term).'</span>')
            ->implode(' ');
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

        $basicFilters = array_values(array_filter([
            $filters['author_id'] ?? null,
            $filters['created_month'] ?? null,
        ]));

        if ($basicFilters !== []) {
            $sections[] = Section::make('Základní')
                ->schema($basicFilters);
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

        $stateFilters = array_values(array_filter([
            $filters['status'] ?? null,
            $filters['trashed'] ?? null,
        ]));

        if ($stateFilters !== []) {
            $sections[] = Section::make('Stav')
                ->schema($stateFilters);
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

        $taxonomies = $collection->taxonomies;

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

        $registry = app(\MiPress\Core\FieldTypes\FieldTypeRegistry::class);
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
