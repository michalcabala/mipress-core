<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use MiPress\Core\Models\Revision;

trait ConfiguresRevisionTable
{
    protected function resolveRevisionOwner(): Model
    {
        if (method_exists($this, 'getOwnerRecord')) {
            return $this->getOwnerRecord();
        }

        return $this->getRecord();
    }

    protected function configureRevisionTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('j. n. Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Uložil')
                    ->default('Systém'),
                TextColumn::make('note')
                    ->label('Poznámka')
                    ->limit(80)
                    ->default('—'),
                TextColumn::make('data_preview')
                    ->label('Obsah')
                    ->state(function (Revision $record): string {
                        $keys = array_keys($record->data ?? []);

                        return implode(', ', array_slice($keys, 0, 5)).(count($keys) > 5 ? '…' : '');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Zatím nejsou dostupné žádné revize')
            ->emptyStateDescription('Revize se vytváří při změně obsahu. Můžete také vytvořit první revizi ručně.')
            ->headerActions([
                Action::make('createSnapshot')
                    ->label('Vytvořit revizi')
                    ->icon('far-floppy-disk')
                    ->color('gray')
                    ->action(function (): void {
                        $owner = $this->resolveRevisionOwner();

                        if (! method_exists($owner, 'createRevision')) {
                            return;
                        }

                        $owner->createRevision('Ruční revize');

                        Notification::make()
                            ->title('Revize vytvořena')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label('Obnovit')
                    ->icon('far-rotate-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Obnovit revizi')
                    ->modalDescription('Obsah záznamu bude nahrazen daty z této revize. Aktuální stav bude uložen jako nová revize. Pokračovat?')
                    ->action(function (Revision $record): void {
                        $owner = $this->resolveRevisionOwner();

                        if (method_exists($owner, 'restoreRevision')) {
                            $owner->restoreRevision($record->getKey());
                        }
                    }),
                Action::make('diff')
                    ->label('Porovnat')
                    ->icon('far-left-right')
                    ->color('info')
                    ->modalHeading('Porovnání s aktuálním stavem')
                    ->modalContent(function (Revision $record): HtmlString {
                        return $this->buildRevisionDiffHtml($record);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
            ]);
    }

    protected function buildRevisionDiffHtml(Revision $record): HtmlString
    {
        $owner = $this->resolveRevisionOwner();
        $revisionData = $record->data ?? [];
        $currentData = collect($owner->getAttributes())
            ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
            ->toArray();

        $rows = '';
        $allKeys = array_unique(array_merge(array_keys($revisionData), array_keys($currentData)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $old = $revisionData[$key] ?? null;
            $cur = $currentData[$key] ?? null;

            if ($old === $cur) {
                continue;
            }

            $oldDisplay = is_array($old) ? json_encode($old, JSON_UNESCAPED_UNICODE) : (string) ($old ?? '—');
            $curDisplay = is_array($cur) ? json_encode($cur, JSON_UNESCAPED_UNICODE) : (string) ($cur ?? '—');

            $rows .= '<tr>'
                .'<td style="padding:4px 8px;font-weight:600;vertical-align:top;white-space:nowrap;">'.e($key).'</td>'
                .'<td style="padding:4px 8px;background:#fef9c3;vertical-align:top;max-width:300px;overflow:hidden;text-overflow:ellipsis;">'.e(mb_substr($oldDisplay, 0, 300)).'</td>'
                .'<td style="padding:4px 8px;background:#dcfce7;vertical-align:top;max-width:300px;overflow:hidden;text-overflow:ellipsis;">'.e(mb_substr($curDisplay, 0, 300)).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            return new HtmlString('<p>Žádné rozdíly.</p>');
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
            .'<thead><tr>'
            .'<th style="padding:4px 8px;text-align:left;">Pole</th>'
            .'<th style="padding:4px 8px;text-align:left;background:#fef9c3;">Revize</th>'
            .'<th style="padding:4px 8px;text-align:left;background:#dcfce7;">Aktuální</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>';

        return new HtmlString($html);
    }
}
