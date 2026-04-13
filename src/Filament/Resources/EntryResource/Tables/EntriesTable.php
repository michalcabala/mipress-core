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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
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
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\FieldTypes\FieldTypeRegistry;
use MiPress\Core\Filament\Resources\Concerns\HasReactivePublicationFields;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;
use MiPress\Core\Services\BlueprintFieldResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class EntriesTable
{
    use HasReactivePublicationFields;

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
            ->label('Změnit publikaci')
            ->icon('far-arrows-rotate')
            ->color('gray')
            ->visible(fn (Entry $record): bool => auth()->user()?->can('publish', $record) === true && ! $record->trashed())
            ->modalHeading(fn (Entry $record): string => 'Změnit publikaci: '.$record->title)
            ->modalSubmitActionLabel('Uložit změny')
            ->fillForm(fn (Entry $record): array => [
                'status' => $record->status->value,
                'published_at' => $record->scheduled_at ?? $record->published_at,
            ])
            ->schema(fn (Entry $record): array => static::getPublicationWorkflowSchema($record))
            ->action(function (Entry $record, array $data): void {
                $previousStatus = $record->status;

                if (! static::applyPublicationWorkflowData($record, $data)) {
                    Notification::make()
                        ->title('Bez změny')
                        ->warning()
                        ->send();

                    return;
                }

                static::sendReviewRequestedNotificationIfNeeded($record, $previousStatus);

                Notification::make()
                    ->title(static::getPublicationNotificationTitle($previousStatus, $record->status))
                    ->body(static::getPublicationNotificationBody($record))
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
            ->modalHeading('Změnit publikaci vybraných položek')
            ->modalSubmitActionLabel('Uložit změny')
            ->schema(static::getPublicationWorkflowSchema())
            ->action(function (EloquentCollection $records, array $data): void {
                $updated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof Entry || auth()->user()?->can('publish', $record) !== true) {
                        $skipped++;

                        continue;
                    }

                    $previousStatus = $record->status;
                    $statusChanged = static::applyPublicationWorkflowData($record, $data);

                    if ($statusChanged) {
                        $updated++;

                        static::sendReviewRequestedNotificationIfNeeded($record, $previousStatus);

                        continue;
                    }

                    $skipped++;
                }

                Notification::make()
                    ->title($updated > 0 ? 'Stav publikace změněn' : 'Bez změny')
                    ->body("Aktualizováno {$updated} položek, přeskočeno {$skipped}.")
                    ->{$updated > 0 ? 'success' : 'warning'}()
                    ->send();
            });
    }

    /**
     * @return array<int, ToggleButtons|DateTimePicker>
     */
    private static function getPublicationWorkflowSchema(?Entry $record = null): array
    {
        return [
            static::makePublicationStatusField($record),
            static::makePublicationDateField($record),
        ];
    }

    private static function makePublicationStatusField(?Entry $record): ToggleButtons
    {
        return self::configureReactivePublicationStatusField(
            ToggleButtons::make('status')
                ->label('Stav publikování')
                ->options(static::getPublicationStatusOptions($record))
                ->colors(static::getPublicationStatusColors())
                ->icons(static::getPublicationStatusIcons())
                ->inline()
                ->required()
                ->helperText(static::publicationStatusHelperText($record)),
            static::canPublish($record),
        );
    }

    private static function makePublicationDateField(?Entry $record): DateTimePicker
    {
        return self::configureReactivePublicationDateField(
            DateTimePicker::make('published_at')
                ->label('Datum publikace')
                ->nullable()
                ->disabled(fn (): bool => ! static::canPublish($record))
                ->helperText('Pokud nastavíte budoucí datum a čas, obsah se uloží jako naplánovaný.'),
            static::canPublish($record),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function getPublicationStatusOptions(?Entry $record): array
    {
        return collect(static::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    private static function getVisiblePublicationStatuses(?Entry $record): array
    {
        if (static::canPublish($record)) {
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
        if (static::canPublish($record)) {
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

    /**
     * @param  array<string, mixed>  $data
     */
    private static function applyPublicationWorkflowData(Entry $record, array $data): bool
    {
        $preparedData = app(WorkflowTransitionService::class)->prepareFormDataForStatus(
            $data,
            canPublish: static::canPublish($record),
            currentStatus: $record->status,
        );

        $nextStatus = data_get($preparedData, 'status');
        $nextStatus = $nextStatus instanceof EntryStatus
            ? $nextStatus
            : EntryStatus::tryFrom((string) $nextStatus);

        if (! $nextStatus instanceof EntryStatus) {
            return false;
        }

        $currentPublishedAt = static::normalizePublicationDateValue($record->published_at);
        $currentScheduledAt = static::normalizePublicationDateValue($record->scheduled_at);
        $nextPublishedAt = static::normalizePublicationDateValue(data_get($preparedData, 'published_at'));
        $nextScheduledAt = static::normalizePublicationDateValue(data_get($preparedData, 'scheduled_at'));
        $nextReviewNote = data_get($preparedData, 'review_note');

        $hasChanged = $record->status !== $nextStatus
            || $currentPublishedAt?->format('c') !== $nextPublishedAt?->format('c')
            || $currentScheduledAt?->format('c') !== $nextScheduledAt?->format('c')
            || (string) ($record->review_note ?? '') !== (string) ($nextReviewNote ?? '');

        if (! $hasChanged) {
            return false;
        }

        $record->status = $nextStatus;
        $record->published_at = $nextPublishedAt;
        $record->scheduled_at = $nextScheduledAt;
        $record->review_note = $nextReviewNote;
        $record->save();

        return true;
    }

    private static function normalizePublicationDateValue(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    private static function sendReviewRequestedNotificationIfNeeded(Entry $record, EntryStatus $previousStatus): void
    {
        if ($previousStatus === $record->status || $record->status !== EntryStatus::InReview) {
            return;
        }

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: 'Nový obsah ke schválení',
            body: 'Položka "'.$record->title.'" čeká na schválení publikace.',
            editUrl: EntryResource::getUrl('edit', [
                'record' => $record,
                'collection' => $record->collection?->handle,
            ]),
            previewRouteName: 'preview.entry',
            previewRouteParameterName: 'entry',
        );
    }

    private static function getPublicationNotificationTitle(EntryStatus $previousStatus, EntryStatus $currentStatus): string
    {
        return match ($currentStatus) {
            EntryStatus::Published => 'Položka publikována',
            EntryStatus::Scheduled => 'Publikace naplánována',
            EntryStatus::InReview => 'Odesláno ke schválení',
            EntryStatus::Rejected => 'Položka zamítnuta',
            EntryStatus::Draft => in_array($previousStatus, [EntryStatus::Published, EntryStatus::Scheduled], true)
                ? 'Publikace zrušena'
                : 'Uloženo jako koncept',
        };
    }

    private static function getPublicationNotificationBody(Entry $record): ?string
    {
        return match ($record->status) {
            EntryStatus::Scheduled => 'Publikace je naplánována na '.(($record->scheduled_at ?? $record->published_at)?->format('j. n. Y H:i') ?? '—').'.',
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
