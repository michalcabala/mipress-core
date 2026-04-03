<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
            ->emptyStateDescription('Revize se vytváří automaticky při změně obsahu.')
            ->headerActions([
                Action::make('compareRevisions')
                    ->label('Porovnat revize')
                    ->icon('far-left-right')
                    ->color('info')
                    ->schema(fn (): array => $this->getCompareRevisionSchema())
                    ->action(fn () => null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalHeading('Porovnání dvou revizí')
                    ->modalContent(fn (?array $data): HtmlString => new HtmlString(
                        '<div x-data="{ show: false }" class="text-sm text-gray-500 dark:text-gray-400">Vyberte dvě revize k porovnání pomocí formuláře výše.</div>'
                    ))
                    ->after(fn () => null),
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

    protected function getCompareRevisionSchema(): array
    {
        $owner = $this->resolveRevisionOwner();
        $options = $owner->revisions()
            ->orderByDesc('created_at')
            ->get()
            ->mapWithKeys(fn (Revision $r): array => [
                $r->getKey() => $r->created_at->format('j. n. Y H:i:s')
                    .($r->user?->name ? ' — '.$r->user->name : ' — Systém')
                    .($r->note ? ' ('.$r->note.')' : ''),
            ])
            ->toArray();

        $currentLabel = 'Aktuální stav';

        return [
            Select::make('revision_a')
                ->label('Starší revize (vlevo)')
                ->options($options)
                ->required()
                ->live()
                ->searchable(),
            Select::make('revision_b')
                ->label('Novější revize (vpravo)')
                ->options(['__current__' => $currentLabel] + $options)
                ->required()
                ->default('__current__')
                ->live()
                ->searchable(),
            \Filament\Infolists\Components\TextEntry::make('comparison_result')
                ->label('')
                ->state(fn (\Filament\Schemas\Components\Utilities\Get $get): string => 'diff_placeholder')
                ->formatStateUsing(function (string $state, \Filament\Schemas\Components\Utilities\Get $get): HtmlString {
                    $revisionAId = $get('revision_a');
                    $revisionBId = $get('revision_b');

                    if (! $revisionAId || ! $revisionBId) {
                        return new HtmlString('<p class="text-sm text-gray-500">Vyberte obě revize k porovnání.</p>');
                    }

                    return $this->buildTwoRevisionDiffHtml((int) $revisionAId, $revisionBId === '__current__' ? null : (int) $revisionBId);
                })
                ->columnSpanFull(),
        ];
    }

    protected function buildTwoRevisionDiffHtml(int $revisionAId, ?int $revisionBId): HtmlString
    {
        $owner = $this->resolveRevisionOwner();
        $revisionA = Revision::find($revisionAId);

        if (! $revisionA) {
            return new HtmlString('<p>Revize nenalezena.</p>');
        }

        $leftData = $revisionA->data ?? [];
        $leftLabel = $revisionA->created_at->format('j. n. Y H:i:s');

        if ($revisionBId === null) {
            $rightData = collect($owner->getAttributes())
                ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
                ->toArray();
            $rightLabel = 'Aktuální stav';
        } else {
            $revisionB = Revision::find($revisionBId);

            if (! $revisionB) {
                return new HtmlString('<p>Revize nenalezena.</p>');
            }

            $rightData = $revisionB->data ?? [];
            $rightLabel = $revisionB->created_at->format('j. n. Y H:i:s');
        }

        return $this->renderDiffTable($leftData, $rightData, $leftLabel, $rightLabel);
    }

    protected function buildRevisionDiffHtml(Revision $record): HtmlString
    {
        $owner = $this->resolveRevisionOwner();
        $leftData = $record->data ?? [];
        $rightData = collect($owner->getAttributes())
            ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
            ->toArray();

        return $this->renderDiffTable(
            $leftData,
            $rightData,
            $record->created_at->format('j. n. Y H:i:s'),
            'Aktuální stav',
        );
    }

    protected function renderDiffTable(array $leftData, array $rightData, string $leftLabel, string $rightLabel): HtmlString
    {
        $rows = '';
        $allKeys = array_unique(array_merge(array_keys($leftData), array_keys($rightData)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $old = $leftData[$key] ?? null;
            $cur = $rightData[$key] ?? null;

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
            .'<th style="padding:4px 8px;text-align:left;background:#fef9c3;">'.e($leftLabel).'</th>'
            .'<th style="padding:4px 8px;text-align:left;background:#dcfce7;">'.e($rightLabel).'</th>'
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>';

        return new HtmlString($html);
    }
}
