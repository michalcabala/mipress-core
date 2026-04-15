<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Tables;

use Awcodes\Curator\Components\Tables\CuratorColumn;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\Concerns\HasPublicationTableWorkflow;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Support\EntryLikeTableBuilders;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;

class PagesTable
{
    use HasPublicationTableWorkflow;

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
                EntryLikeTableBuilders::makeStatusColumn(),
                EntryLikeTableBuilders::makeAuthorColumn(),
                EntryLikeTableBuilders::makeUpdatedAtColumn(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                EntryLikeTableBuilders::makeStatusFilter(),
                EntryLikeTableBuilders::makeAuthorFilter(fn (): array => static::getAuthorFilterOptions()),
                EntryLikeTableBuilders::makeCreatedMonthFilter(fn (): array => static::getCreatedMonthOptions()),
                TrashedFilter::make(),
            ])
            ->filtersFormSchema(fn (array $filters): array => static::getFiltersFormSchema($filters))
            ->actions([
                ActionGroup::make([
                    static::makeViewLiveAction(),
                    static::makePreviewAction(),
                    static::makeTogglePublicationAction(),
                    static::refreshesPublicationStatusOverview(
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
                                    $record->status !== ContentStatus::Published
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
                            ->visible(fn (Page $record): bool => auth()->user()?->can('publish', $record) === true)
                    ),
                    EditAction::make()
                        ->visible(fn (Page $record): bool => auth()->user()?->can('update', $record) === true && ! $record->trashed()),
                    static::refreshesPublicationStatusOverview(
                        RestoreAction::make()
                            ->visible(fn (Page $record): bool => auth()->user()?->can('restore', $record) === true && $record->trashed())
                    ),
                    static::refreshesPublicationStatusOverview(
                        DeleteAction::make()
                            ->visible(fn (Page $record): bool => auth()->user()?->can('delete', $record) === true && ! $record->trashed())
                    ),
                    static::refreshesPublicationStatusOverview(
                        ForceDeleteAction::make()
                            ->visible(fn (Page $record): bool => auth()->user()?->can('forceDelete', $record) === true && $record->trashed())
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
        return Page::class;
    }

    protected static function getPreviewRouteName(): string
    {
        return 'preview.page';
    }

    protected static function getPreviewRouteParameterName(): string
    {
        return 'page';
    }

    protected static function getEditUrl(Model $record): string
    {
        return PageResource::getUrl('edit', ['record' => $record]);
    }

    protected static function getPublishPermission(): string
    {
        return 'entry.publish';
    }

    protected static function getContentLabel(): string
    {
        return 'Stránka';
    }

    protected static function getContentLabelPlural(): string
    {
        return 'stránek';
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
        return EntryLikeTableBuilders::getAuthorFilterOptions(Page::query());
    }

    /**
     * @return array<string, string>
     */
    private static function getCreatedMonthOptions(): array
    {
        return EntryLikeTableBuilders::getCreatedMonthOptions(Page::query());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, Section>
     */
    private static function getFiltersFormSchema(array $filters): array
    {
        return EntryLikeTableBuilders::buildBaseFiltersFormSchema($filters);
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
