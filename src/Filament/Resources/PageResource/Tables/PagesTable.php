<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Tables;

use App\Models\User;
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
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
    private const HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

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
        $homepageId = Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY);

        return $table
            ->columns([
                CuratorColumn::make('featuredImage')
                    ->label('Obrázek')
                    ->size(40),
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
                    ->color(fn (EntryStatus $state) => $state->getColor())
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->isoDateTime('LLL')
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
                Filter::make('created_at_range')
                    ->label('Datum vytvoření')
                    ->schema([
                        DatePicker::make('from')->label('Od'),
                        DatePicker::make('until')->label('Do'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['from'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $data['from']),
                            )
                            ->when(
                                filled($data['until'] ?? null),
                                fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $data['until']),
                            );
                    }),
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
                            $homepageId = Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY);
                            $isCurrentHomepage = ((string) $record->getKey()) === $homepageId;

                            if ($isCurrentHomepage) {
                                Setting::putValue(self::HOMEPAGE_PAGE_SETTING_KEY, null);

                                Notification::make()
                                    ->title('Homepage zrušena')
                                    ->body('Stránka "' . $record->title . '" již není domovskou stránkou.')
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

                            Setting::putValue(self::HOMEPAGE_PAGE_SETTING_KEY, (string) $record->getKey());

                            Notification::make()
                                ->title('Homepage nastavena')
                                ->body('Stránka "' . $record->title . '" je nyní domovskou stránkou.')
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
        $pages = Page::query()
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->get(['created_at']);

        $values = $pages
            ->map(fn (Page $page): ?string => $page->created_at?->format('Y-m'))
            ->filter(fn (?string $value): bool => filled($value))
            ->unique()
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
