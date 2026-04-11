<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Blendbyte\FilamentResourceLock\Events\ResourceLockForceUnlocked;
use Blendbyte\FilamentResourceLock\Models\ResourceLock;
use Blendbyte\FilamentResourceLock\Resources\LockResource as BaseLockResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use MiPress\Core\Filament\Clusters\ContentCluster;
use MiPress\Core\Filament\Resources\ResourceLockResource\Pages\ManageResourceLocks;

class ResourceLockResource extends BaseLockResource
{
    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $slug = 'zaznamove-zamky';

    protected static ?int $navigationSort = 90;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label(__('resource-lock.table.lock_id')),
                TextColumn::make('user.id')->label(__('resource-lock.table.user_id')),
                TextColumn::make('lockable.id')->label(__('resource-lock.table.lockable_id')),
                TextColumn::make('lockable_type')->label(__('resource-lock.table.lockable_type')),
                TextColumn::make('created_at')->label(__('resource-lock.table.created_at')),
                TextColumn::make('updated_at')->label(__('resource-lock.table.updated_at')),
                TextColumn::make('lock_status')->label(__('resource-lock.table.expired'))
                    ->state(fn ($record) => $record->isExpired())
                    ->badge()
                    ->color(static function ($record): string {
                        if ($record->isExpired()) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->icon(static function ($record): string {
                        if ($record->isExpired()) {
                            return 'heroicon-o-lock-open';
                        }

                        return 'heroicon-o-lock-closed';
                    })
                    ->formatStateUsing(static function ($record): string {
                        if ($record->isExpired()) {
                            return __('filament-resource-lock::manager.expired');
                        }

                        return __('filament-resource-lock::manager.active');
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalHeading(fn (ResourceLock $record): string => 'Zrušit zámek pro '.static::describeLock($record).'?')
                    ->modalDescription(fn (ResourceLock $record): string => 'Záznam '.static::describeLock($record).' bude okamžitě odemknut a půjde znovu upravovat.')
                    ->before(function (ResourceLock $record): void {
                        if (config('filament-resource-lock.events.enabled', true)) {
                            ResourceLockForceUnlocked::dispatch(
                                $record->lockable,
                                $record->user_id,
                                auth()->id(),
                            );
                        }
                    })
                    ->icon('heroicon-o-lock-open')
                    ->successNotificationTitle(fn (ResourceLock $record): string => 'Zámek byl zrušen pro '.static::describeLock($record))
                    ->label(__('filament-resource-lock::manager.unlock')),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->before(function (Collection $records): void {
                        if (config('filament-resource-lock.events.enabled', true)) {
                            $records->each(function (ResourceLock $record): void {
                                ResourceLockForceUnlocked::dispatch(
                                    $record->lockable,
                                    $record->user_id,
                                    auth()->id(),
                                );
                            });
                        }
                    })
                    ->deselectRecordsAfterCompletion()
                    ->requiresConfirmation()
                    ->modalHeading('Zrušit vybrané zámky?')
                    ->modalDescription('Vybrané zámky budou okamžitě odstraněny a odpovídající záznamy půjde znovu upravovat.')
                    ->icon('heroicon-o-lock-open')
                    ->successNotificationTitle('Vybrané zámky byly zrušeny')
                    ->label(__('filament-resource-lock::manager.unlock')),
            ]);
    }

    private static function describeLock(ResourceLock $record): string
    {
        $lockable = $record->lockable;

        if ($lockable !== null) {
            foreach (['title', 'name', 'handle', 'email', 'slug'] as $attribute) {
                $value = $lockable->getAttribute($attribute);

                if (is_scalar($value) && trim((string) $value) !== '') {
                    return 'záznam „'.trim((string) $value).'“';
                }
            }
        }

        return 'záznam #'.$record->lockable_id;
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public static function getNavigationLabel(): string
    {
        return __('resource-lock.navigation.label');
    }

    public static function getPluralLabel(): string
    {
        return __('resource-lock.navigation.plural_label');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResourceLocks::route('/'),
        ];
    }
}
