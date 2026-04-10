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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Page;
use MiPress\Core\Models\Setting;

class PagesTable
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'general.homepage_page_id';

    private const LEGACY_HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    /**
     * @var array<int, int>
     */
    private static array $pageDepthCache = [];

    /**
     * @var array<int, int|null>|null
     */
    private static ?array $pageParentMap = null;

    public static function table(Table $table): Table
    {
        $homepageId = static::getHomepagePageId();

        return $table
            ->columns([
                IconColumn::make('resource_lock_state')
                    ->label('Zámek')
                    ->alignCenter()
                    ->state(fn (Page $record): ?string => static::getResourceLockState($record))
                    ->icon(fn (?string $state): ?string => match ($state) {
                        'mine', 'other' => 'fal-lock',
                        default => null,
                    })
                    ->color(fn (?string $state): ?string => match ($state) {
                        'mine' => 'primary',
                        'other' => 'danger',
                        default => null,
                    })
                    ->tooltip(fn (Page $record, ?string $state): ?string => static::getResourceLockTooltip($record, $state)),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (Page $record): string => static::formatHierarchyTitle($record->title, static::getPageDepth($record)))
                    ->description(function (Page $record) use ($homepageId): ?string {
                        return ((string) $record->getKey()) === $homepageId ? 'Domovská stránka' : null;
                    }),
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
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->sortable(),
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
                SelectFilter::make('author_id')
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
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(EntryStatus::class)
                    ->native(false),
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
                        ->action(function (Page $record): void {
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

                            if ($record->status !== EntryStatus::Published) {
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

    private static function getResourceLockState(Page $record): ?string
    {
        $resourceLock = $record->resourceLock;

        if ($resourceLock === null || $resourceLock->isExpired($record->getLockTimeout())) {
            return null;
        }

        return $record->isLockedByCurrentUser() ? 'mine' : 'other';
    }

    private static function getResourceLockTooltip(Page $record, ?string $state): ?string
    {
        return match ($state) {
            'mine' => 'Právě upravujete vy',
            'other' => filled($record->resourceLock?->user?->name)
                ? 'Právě upravuje '.$record->resourceLock->user->name
                : 'Právě upravuje jiný uživatel',
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

        return User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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

        $basicFilters = array_values(array_filter([
            $filters['author_id'] ?? null,
            $filters['created_month'] ?? null,
        ]));

        if ($basicFilters !== []) {
            $sections[] = Section::make('Základní')
                ->schema($basicFilters);
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

        if (array_key_exists($recordId, static::$pageDepthCache)) {
            return static::$pageDepthCache[$recordId];
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

        static::$pageDepthCache[$recordId] = $depth;

        return $depth;
    }

    /**
     * @return array<int, int|null>
     */
    private static function getPageParentMap(): array
    {
        if (static::$pageParentMap !== null) {
            return static::$pageParentMap;
        }

        static::$pageParentMap = Page::query()
            ->select(['id', 'parent_id'])
            ->get()
            ->mapWithKeys(fn (Page $page): array => [(int) $page->getKey() => $page->parent_id ? (int) $page->parent_id : null])
            ->all();

        return static::$pageParentMap;
    }
}
