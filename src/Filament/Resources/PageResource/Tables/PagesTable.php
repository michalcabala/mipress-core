<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Tables;

use App\Models\User;
use Awcodes\Curator\Components\Tables\CuratorColumn;
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
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HasReactivePublicationFields;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class PagesTable
{
    use HasReactivePublicationFields;

    private const HOMEPAGE_PAGE_SETTING_KEY = 'general.homepage_page_id';

    private const LEGACY_HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    public static function table(Table $table): Table
    {
        $homepageId = static::getHomepagePageId();

        return $table
            ->columns([
                CuratorColumn::make('featured_image_id')
                    ->label('Obrázek')
                    ->size(40),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (Page $record): string => static::formatHierarchyTitle($record->title, static::getPageDepth($record)))
                    ->description(function (Page $record) use ($homepageId): ?string {
                        $parts = [];

                        if (((string) $record->getKey()) === $homepageId) {
                            $parts[] = 'Domovská stránka';
                        }

                        if (filled($record->slug)) {
                            $parts[] = '/'.$record->slug;
                        }

                        return $parts === [] ? null : implode(' · ', $parts);
                    }),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->default('—'),
                TextColumn::make('parent.title')
                    ->label('Nadřazená')
                    ->sortable()
                    ->toggleable()
                    ->default('—'),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->icon(fn (EntryStatus $state): ?string => $state->getIcon())
                    ->color(fn (EntryStatus $state) => $state->getColor())
                    ->sortable(),
                UserColumn::make('author.name')
                    ->label('Autor')
                    ->state(fn (Page $record): ?User => $record->author)
                    ->sortable()
                    ->toggleable()
                    ->wrapped(),
                TextColumn::make('updated_at')
                    ->label('Datum')
                    ->isoDateTime('LLL')
                    ->description(fn ($record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                        ? 'Vytvořeno '.$record->created_at->isoFormat('LLL')
                        : null)
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(EntryStatus::class),
                UserSelectFilter::make('author_id')
                    ->label('Autor')
                    ->options(fn (): array => static::getAuthorFilterOptions())
                    ->multiple()
                    ->searchable(),
                SelectFilter::make('created_month')
                    ->label('Měsíc')
                    ->options(fn (): array => static::getCreatedMonthOptions())
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
            ])
            ->filtersFormSchema(fn (array $filters): array => static::getFiltersFormSchema($filters))
            ->actions([
                ActionGroup::make([
                    static::makeViewLiveAction(),
                    static::makePreviewAction(),
                    static::makeTogglePublicationAction(),
                    Action::make('toggleHomepage')
                        ->label(function (Page $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'Zrušit homepage'
                                : 'Nastavit jako homepage';
                        })
                        ->icon(function (Page $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'fal-house-circle-xmark'
                                : 'fal-house';
                        })
                        ->color(function (Page $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId ? 'danger' : 'gray';
                        })
                        ->requiresConfirmation()
                        ->modalHeading(function (Page $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'Zrušit stránce "'.$record->title.'" status homepage?'
                                : 'Nastavit stránku "'.$record->title.'" jako homepage?';
                        })
                        ->modalDescription(function (Page $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'Stránka přestane být domovskou stránkou webu.'
                                : 'Tato stránka se nastaví jako výchozí domovská stránka webu.';
                        })
                        ->action(function (Page $record): void {
                            $record->refresh();

                            $homepageId = static::getHomepagePageId();
                            $isCurrentHomepage = ((string) $record->getKey()) === $homepageId;

                            if ($isCurrentHomepage) {
                                static::storeHomepagePageId(null);

                                Notification::make()
                                    ->title('Homepage zrušena')
                                    ->body('Stránka "'.$record->title.'" již není domovskou stránkou.')
                                    ->success()
                                    ->send();

                                return;
                            }

                            if (
                                $record->status !== EntryStatus::Published
                                || ! ($record->published_at instanceof Carbon)
                                || $record->published_at->isFuture()
                            ) {
                                Notification::make()
                                    ->title('Nelze nastavit jako homepage')
                                    ->body('Domovskou stránku lze nastavit pouze na publikovanou stránku.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            static::storeHomepagePageId((string) $record->getKey());

                            Notification::make()
                                ->title('Homepage nastavena')
                                ->body('Stránka "'.$record->title.'" je nyní domovskou stránkou.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Page $record): bool => auth()->user()?->can('publish', $record) === true),
                    EditAction::make()
                        ->visible(fn (Page $record): bool => auth()->user()?->can('update', $record) === true && ! $record->trashed()),
                    RestoreAction::make()
                        ->visible(fn (Page $record): bool => auth()->user()?->can('restore', $record) === true && $record->trashed()),
                    DeleteAction::make()
                        ->visible(fn (Page $record): bool => auth()->user()?->can('delete', $record) === true && ! $record->trashed()),
                    ForceDeleteAction::make()
                        ->visible(fn (Page $record): bool => auth()->user()?->can('forceDelete', $record) === true && $record->trashed()),
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
            ->url(fn (Page $record): ?string => $record->getPublicUrl(), shouldOpenInNewTab: true)
            ->visible(fn (Page $record): bool => auth()->user()?->can('view', $record) === true
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
                fn (Page $record): string => URL::temporarySignedRoute(
                    'preview.page',
                    now()->addHour(),
                    ['page' => $record->getKey()],
                ),
                shouldOpenInNewTab: true,
            )
            ->visible(fn (Page $record): bool => auth()->user()?->can('view', $record) === true
                && ! $record->trashed()
                && $record->status !== EntryStatus::Published);
    }

    private static function makeTogglePublicationAction(): Action
    {
        return Action::make('togglePublicationStatus')
            ->label('Změnit publikaci')
            ->icon('far-arrows-rotate')
            ->color('gray')
            ->visible(fn (Page $record): bool => auth()->user()?->can('publish', $record) === true && ! $record->trashed())
            ->modalHeading(fn (Page $record): string => 'Změnit publikaci: '.$record->title)
            ->modalSubmitActionLabel('Uložit změny')
            ->fillForm(fn (Page $record): array => [
                'status' => $record->status->value,
                'published_at' => $record->scheduled_at ?? $record->published_at,
            ])
            ->schema(fn (Page $record): array => static::getPublicationWorkflowSchema($record))
            ->action(function (Page $record, array $data): void {
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
            ->modalHeading('Změnit publikaci vybraných stránek')
            ->modalSubmitActionLabel('Uložit změny')
            ->schema(static::getPublicationWorkflowSchema())
            ->action(function (EloquentCollection $records, array $data): void {
                $updated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof Page || auth()->user()?->can('publish', $record) !== true) {
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
    private static function getPublicationWorkflowSchema(?Page $record = null): array
    {
        return [
            static::makePublicationStatusField($record),
            static::makePublicationDateField($record),
        ];
    }

    private static function makePublicationStatusField(?Page $record): ToggleButtons
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

    private static function makePublicationDateField(?Page $record): DateTimePicker
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
    private static function getPublicationStatusOptions(?Page $record): array
    {
        return collect(static::getVisiblePublicationStatuses($record))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    private static function getVisiblePublicationStatuses(?Page $record): array
    {
        if (static::canPublish($record)) {
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
        if (static::canPublish($record)) {
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
     * @param  array<string, mixed>  $data
     */
    private static function applyPublicationWorkflowData(Page $record, array $data): bool
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

    private static function sendReviewRequestedNotificationIfNeeded(Page $record, EntryStatus $previousStatus): void
    {
        if ($previousStatus === $record->status || $record->status !== EntryStatus::InReview) {
            return;
        }

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: 'Nová stránka ke schválení',
            body: 'Stránka "'.$record->title.'" čeká na schválení publikace.',
            editUrl: PageResource::getUrl('edit', ['record' => $record]),
            previewRouteName: 'preview.page',
            previewRouteParameterName: 'page',
        );
    }

    private static function getPublicationNotificationTitle(EntryStatus $previousStatus, EntryStatus $currentStatus): string
    {
        return match ($currentStatus) {
            EntryStatus::Published => 'Stránka publikována',
            EntryStatus::Scheduled => 'Publikace naplánována',
            EntryStatus::InReview => 'Odesláno ke schválení',
            EntryStatus::Rejected => 'Stránka zamítnuta',
            EntryStatus::Draft => in_array($previousStatus, [EntryStatus::Published, EntryStatus::Scheduled], true)
                ? 'Publikace zrušena'
                : 'Uloženo jako koncept',
        };
    }

    private static function getPublicationNotificationBody(Page $record): ?string
    {
        return match ($record->status) {
            EntryStatus::Scheduled => 'Publikace je naplánována na '.(($record->scheduled_at ?? $record->published_at)?->format('j. n. Y H:i') ?? '—').'.',
            default => null,
        };
    }

    private static function getHomepagePageId(): ?string
    {
        return Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY)
            ?? Setting::getValue(self::LEGACY_HOMEPAGE_PAGE_SETTING_KEY);
    }

    private static function storeHomepagePageId(?string $pageId): void
    {
        Setting::putValue(self::HOMEPAGE_PAGE_SETTING_KEY, $pageId);
        Setting::putValue(self::LEGACY_HOMEPAGE_PAGE_SETTING_KEY, null);
        Setting::putValue(self::LEGACY_HOMEPAGE_ENTRY_SETTING_KEY, null);
    }

    /**
     * @return array<int, string>
     */
    private static function getAuthorFilterOptions(): array
    {
        $authorIds = Page::query()
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
    private static function getCreatedMonthOptions(): array
    {
        $createdMonthExpression = static::getCreatedMonthExpression(Page::query()->getModel()->getConnection()->getDriverName());

        $values = Page::query()
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
     * @param  array<string, mixed>  $filters
     * @return array<int, Section>
     */
    private static function getFiltersFormSchema(array $filters): array
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

        return $sections;
    }

    private static function formatHierarchyTitle(string $title, int $depth): string
    {
        if ($depth <= 0) {
            return $title;
        }

        return str_repeat('|  ', $depth).'|- '.$title;
    }

    private static function getPageDepth(Page $record): int
    {
        $recordId = (int) $record->getKey();
        $depthCache = static::getPageDepthCache();

        if (array_key_exists($recordId, $depthCache)) {
            return $depthCache[$recordId];
        }

        $parentMap = static::getPageParentMap();
        $depth = 0;
        $seen = [$recordId => true];
        $currentParentId = $parentMap[$recordId] ?? null;

        while ($currentParentId !== null) {
            if (isset($seen[$currentParentId])) {
                break;
            }

            $seen[$currentParentId] = true;
            $depth++;
            $currentParentId = $parentMap[$currentParentId] ?? null;
        }

        $depthCache[$recordId] = $depth;
        static::setPageDepthCache($depthCache);

        return $depth;
    }

    /**
     * @return array<int, int|null>
     */
    private static function getPageParentMap(): array
    {
        $parentMap = static::getRequestParentMap();

        if ($parentMap !== null) {
            return $parentMap;
        }

        $parentMap = Page::query()
            ->select(['id', 'parent_id'])
            ->get()
            ->mapWithKeys(fn (Page $page): array => [(int) $page->getKey() => $page->parent_id ? (int) $page->parent_id : null])
            ->all();

        static::setRequestParentMap($parentMap);

        return $parentMap;
    }

    /**
     * @return array<int, int>
     */
    private static function getPageDepthCache(): array
    {
        $cache = request()->attributes->get('mipress.pages.depth_cache', []);

        return is_array($cache) ? $cache : [];
    }

    /**
     * @param  array<int, int>  $cache
     */
    private static function setPageDepthCache(array $cache): void
    {
        request()->attributes->set('mipress.pages.depth_cache', $cache);
    }

    /**
     * @return array<int, int|null>|null
     */
    private static function getRequestParentMap(): ?array
    {
        $map = request()->attributes->get('mipress.pages.parent_map');

        return is_array($map) ? $map : null;
    }

    /**
     * @param  array<int, int|null>  $parentMap
     */
    private static function setRequestParentMap(array $parentMap): void
    {
        request()->attributes->set('mipress.pages.parent_map', $parentMap);
    }
}
