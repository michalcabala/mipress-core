<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Revision;
use MiPress\Core\Services\RevisionDiffPresenter;

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
                    ->label('Uloženo')
                    ->isoDateTime('LLL')
                    ->description(fn (Revision $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('status_snapshot')
                    ->label('Stav obsahu')
                    ->state(fn (Revision $record): ?EntryStatus => $this->resolveRevisionStatus($record))
                    ->formatStateUsing(fn (?EntryStatus $state): string => $state?->getLabel() ?? 'Bez stavu')
                    ->badge()
                    ->icon(fn (?EntryStatus $state): ?string => $state?->getIcon())
                    ->color(fn (?EntryStatus $state): string|array|null => $state?->getColor() ?? 'gray')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Uložil')
                    ->default('Systém')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('note')
                    ->label('Poznámka')
                    ->default('Bez poznámky')
                    ->limit(110)
                    ->toggleable(),
                TextColumn::make('snapshot_summary')
                    ->label('Zachycený obsah')
                    ->state(fn (Revision $record): string => $this->revisionDiffPresenter()->summarizeSnapshot($record->data ?? []))
                    ->description(fn (Revision $record): string => $this->revisionDiffPresenter()->summarizeSnapshotMeta($record->data ?? []))
                    ->limit(110),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordAction('diff')
            ->recordActionsAlignment('end')
            ->recordActionsColumnLabel('Akce')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Zatím nejsou dostupné žádné revize')
            ->emptyStateDescription('Revize se vytváří automaticky při změně obsahu nebo při workflow přechodech.')
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Uložil')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->headerActions([
                Action::make('compareRevisions')
                    ->label('Porovnat dvě revize')
                    ->icon('far-left-right')
                    ->color('info')
                    ->slideOver()
                    ->stickyModalHeader()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->schema(fn (): array => $this->getCompareRevisionSchema())
                    ->action(fn () => null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít')
                    ->modalHeading('Porovnání dvou revizí')
                    ->modalDescription('Vyberte dvě uložené verze a zobrazíme jen pole, která se mezi nimi změnila.'),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label('Detail změn')
                    ->icon('far-code-compare')
                    ->color('info')
                    ->slideOver()
                    ->stickyModalHeader()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalHeading('Porovnání s aktuálním stavem')
                    ->modalDescription('Uvidíte jen pole, která se liší oproti aktuálně uložené verzi obsahu.')
                    ->modalContent(function (Revision $record): HtmlString {
                        return $this->buildRevisionDiffHtml($record);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                Action::make('restore')
                    ->label('Obnovit verzi')
                    ->icon('far-rotate-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Obnovit vybranou revizi')
                    ->modalDescription('Obsah záznamu bude nahrazen daty z této revize. Aktuální stav se předtím uloží jako nová revize, takže se k němu budete moci vrátit.')
                    ->visible(fn (): bool => method_exists($this->resolveRevisionOwner(), 'restoreRevision'))
                    ->action(function (Revision $record): void {
                        $owner = $this->resolveRevisionOwner();

                        if (method_exists($owner, 'restoreRevision')) {
                            $owner->restoreRevision($record->getKey());
                        }
                    }),
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
                ->searchable()
                ->native(false),
            Select::make('revision_b')
                ->label('Novější revize (vpravo)')
                ->options(['__current__' => $currentLabel] + $options)
                ->required()
                ->default('__current__')
                ->live()
                ->searchable()
                ->native(false),
            Placeholder::make('comparison_result')
                ->label('Rozdíly')
                ->content(function (Get $get): HtmlString {
                    $revisionAId = $get('revision_a');
                    $revisionBId = $get('revision_b');

                    if (! $revisionAId || ! $revisionBId) {
                        return new HtmlString('<div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 ring-1 ring-gray-200/80 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">Vyberte obě revize k porovnání.</div>');
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
            return new HtmlString('<div class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-danger-200/80 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/20">Revize nebyla nalezena.</div>');
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
                return new HtmlString('<div class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 ring-1 ring-danger-200/80 dark:bg-danger-500/10 dark:text-danger-300 dark:ring-danger-500/20">Revize nebyla nalezena.</div>');
            }

            $rightData = $revisionB->data ?? [];
            $rightLabel = $revisionB->created_at->format('j. n. Y H:i:s');
        }

        return $this->revisionDiffPresenter()->renderComparison($leftData, $rightData, $leftLabel, $rightLabel);
    }

    protected function buildRevisionDiffHtml(Revision $record): HtmlString
    {
        $owner = $this->resolveRevisionOwner();
        $leftData = $record->data ?? [];
        $rightData = collect($owner->getAttributes())
            ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
            ->toArray();

        return $this->revisionDiffPresenter()->renderComparison(
            $leftData,
            $rightData,
            $record->created_at->format('j. n. Y H:i:s'),
            'Aktuální stav',
        );
    }

    protected function resolveRevisionStatus(Revision $record): ?EntryStatus
    {
        $status = data_get($record->data, 'status');

        if ($status instanceof EntryStatus) {
            return $status;
        }

        if (! is_string($status)) {
            return null;
        }

        return EntryStatus::tryFrom(trim($status));
    }

    protected function revisionDiffPresenter(): RevisionDiffPresenter
    {
        return app(RevisionDiffPresenter::class);
    }
}
