<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Tables;

use Awcodes\Curator\Components\Tables\CuratorColumn;
use Filament\Actions\Action;
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
use Livewire\Component;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Setting;

class EntriesTable
{
    private const string HOMEPAGE_SETTING_KEY = 'site.homepage_entry_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                CuratorColumn::make('featuredImage')
                    ->label('Obrázek')
                    ->size(40),
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable()
                    ->description(function (Entry $record, Component $livewire): ?string {
                        if (! static::isInPagesCollection($livewire)) {
                            return null;
                        }

                        $homepageId = Setting::getValue(self::HOMEPAGE_SETTING_KEY);

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
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Action::make('toggleHomepage')
                    ->label(function (Entry $record): string {
                        $homepageId = Setting::getValue(self::HOMEPAGE_SETTING_KEY);

                        return ((string) $record->getKey()) === $homepageId
                            ? 'Zrušit homepage'
                            : 'Nastavit jako homepage';
                    })
                    ->icon(function (Entry $record): string {
                        $homepageId = Setting::getValue(self::HOMEPAGE_SETTING_KEY);

                        return ((string) $record->getKey()) === $homepageId
                            ? 'fal-house-circle-xmark'
                            : 'fal-house';
                    })
                    ->color(function (Entry $record): string {
                        $homepageId = Setting::getValue(self::HOMEPAGE_SETTING_KEY);

                        return ((string) $record->getKey()) === $homepageId ? 'danger' : 'gray';
                    })
                    ->requiresConfirmation()
                    ->action(function (Entry $record): void {
                        $homepageId = Setting::getValue(self::HOMEPAGE_SETTING_KEY);
                        $isCurrentHomepage = ((string) $record->getKey()) === $homepageId;

                        if ($isCurrentHomepage) {
                            Setting::putValue(self::HOMEPAGE_SETTING_KEY, null);

                            Notification::make()
                                ->title('Homepage zrušena')
                                ->body('Položka "' . $record->title . '" již není domovskou stránkou.')
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

                        Setting::putValue(self::HOMEPAGE_SETTING_KEY, (string) $record->getKey());

                        Notification::make()
                            ->title('Homepage nastavena')
                            ->body('Položka "' . $record->title . '" je nyní domovskou stránkou.')
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
}
