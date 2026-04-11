<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Tables;

use App\Models\User;
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
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;

class PagesTable
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'general.homepage_page_id';

    private const LEGACY_HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    public static function table(Table $table): Table
    {
        $homepageId = static::getHomepagePageId();

        return $table
            ->columns([
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
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
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
