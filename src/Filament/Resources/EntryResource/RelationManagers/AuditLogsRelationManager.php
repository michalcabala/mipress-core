<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Historie změn';

    protected static ?string $icon = 'far-clock-rotate-left';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('j. n. Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Uživatel')
                    ->default('Systém'),
                TextColumn::make('action')
                    ->label('Akce')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        'status_changed' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'created' => 'Vytvořeno',
                        'updated' => 'Upraveno',
                        'deleted' => 'Smazáno',
                        'restored' => 'Obnoveno',
                        'status_changed' => 'Změna stavu',
                        default => $state,
                    }),
                TextColumn::make('note')
                    ->label('Poznámka')
                    ->limit(80)
                    ->default('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
