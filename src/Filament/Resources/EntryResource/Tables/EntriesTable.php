<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Tables;

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
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Setting;

class EntriesTable
{
    private const HOMEPAGE_PAGE_SETTING_KEY = 'site.homepage_page_id';

    private const LEGACY_HOMEPAGE_ENTRY_SETTING_KEY = 'site.homepage_entry_id';

    public static function table(Table $table): Table
    {
        $homepageId = self::resolveHomepageId();

        return $table
            ->columns([
                ImageColumn::make('featuredImage')
                    ->label('Obrázek')
                    ->height(40)
                    ->width(40)
                    ->state(fn (Entry $record): ?string => $record->featuredImage?->hasCuration('thumbnail')
                        ? $record->featuredImage->getCuration('thumbnail')['url']
                        : $record->featuredImage?->url),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable()
                    ->description(function (Entry $record, Component $livewire) use ($homepageId): ?string {
                        if (! static::isInPagesCollection($livewire)) {
                            return null;
                        }

                        return ((string) $record->getKey()) === $homepageId ? 'Domovská stránka' : null;
                    }),
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
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
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
                        ->label(function (Entry $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'Zrušit homepage'
                                : 'Nastavit jako homepage';
                        })
                        ->icon(function (Entry $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId
                                ? 'fal-house-circle-xmark'
                                : 'fal-house';
                        })
                        ->color(function (Entry $record) use ($homepageId): string {
                            return ((string) $record->getKey()) === $homepageId ? 'danger' : 'gray';
                        })
                        ->requiresConfirmation()
                        ->action(function (Entry $record): void {
                            $homepageId = self::resolveHomepageId();
                            $isCurrentHomepage = ((string) $record->getKey()) === $homepageId;

                            if ($isCurrentHomepage) {
                                Setting::putValue(self::HOMEPAGE_PAGE_SETTING_KEY, null);
                                Setting::putValue(self::LEGACY_HOMEPAGE_ENTRY_SETTING_KEY, null);

                                Notification::make()
                                    ->title('Homepage zrušena')
                                    ->body('Položka "'.$record->title.'" již není domovskou stránkou.')
                                    ->success()
                                    ->send();

                                return;
                            }

                            if ($record->status !== EntryStatus::Published) {
                                Notification::make()
                                    ->title('Nelze nastavit jako homepage')
                                    ->body('Domovskou stránku lze nastavit pouze na publikovaný obsah.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Setting::putValue(self::HOMEPAGE_PAGE_SETTING_KEY, (string) $record->getKey());

                            Notification::make()
                                ->title('Homepage nastavena')
                                ->body('Položka "'.$record->title.'" je nyní domovskou stránkou.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Entry $record, Component $livewire): bool => static::isInPagesCollection($livewire) && auth()->user()?->can('publish', $record) === true),
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

    private static function isInPagesCollection(Component $livewire): bool
    {
        return property_exists($livewire, 'collectionHandle') && $livewire->collectionHandle === 'pages';
    }

    private static function resolveHomepageId(): ?string
    {
        return Setting::getValue(self::HOMEPAGE_PAGE_SETTING_KEY)
            ?? Setting::getValue(self::LEGACY_HOMEPAGE_ENTRY_SETTING_KEY);
    }

    /**
     * @return array<int, string>
     */
    private static function getAuthorFilterOptions(): array
    {
        $collection = EntryResource::getCurrentCollection();

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
    private static function getCreatedMonthOptions(): array
    {
        $collection = EntryResource::getCurrentCollection();

        $entries = Entry::query()
            ->when(
                $collection,
                fn (Builder $query): Builder => $query->where('collection_id', $collection->id),
            )
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->get(['created_at']);

        $values = $entries
            ->map(fn (Entry $entry): ?string => $entry->created_at?->format('Y-m'))
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
}
