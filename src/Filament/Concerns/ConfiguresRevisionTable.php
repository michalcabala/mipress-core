<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\ContentStatus;
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
                    ->state(fn (Revision $record): ?ContentStatus => $this->resolveRevisionStatus($record))
                    ->formatStateUsing(fn (?ContentStatus $state): string => $state?->getLabel() ?? 'Bez stavu')
                    ->badge()
                    ->icon(fn (?ContentStatus $state): ?string => $state?->getIcon())
                    ->color(fn (?ContentStatus $state): string|array|null => $state?->getColor() ?? 'gray')
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
                    ->modalHeading(fn (): string => 'Porovnání revizí: '.$this->resolveRevisionOwnerTitle($this->resolveRevisionOwner()))
                    ->modalDescription('Vyberte dvě uložené verze záznamu a zobrazíme jen pole, která se mezi nimi změnila.'),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label('Detail změn')
                    ->icon('far-code-compare')
                    ->color('info')
                    ->slideOver()
                    ->stickyModalHeader()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->modalHeading(fn (Revision $record): string => 'Porovnání revize s aktuálním stavem: '.$this->resolveRevisionOwnerTitle($this->resolveRevisionOwner()))
                    ->modalDescription(fn (Revision $record): string => 'Zobrazujeme rozdíly mezi revizí z '.$record->created_at?->format('j. n. Y H:i:s').' a aktuálně uloženou verzí záznamu.')
                    ->schema(fn (Revision $record): array => $this->buildRevisionDiffSchema($record))
                    ->action(fn () => null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                Action::make('restore')
                    ->label('Obnovit verzi')
                    ->icon('far-rotate-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Revision $record): string => 'Obnovit revizi z '.$record->created_at?->format('j. n. Y H:i:s').'?')
                    ->modalDescription(fn (): string => 'Obsah záznamu „'.$this->resolveRevisionOwnerTitle($this->resolveRevisionOwner()).'“ bude nahrazen daty z této revize. Aktuální stav se předtím uloží jako nová revize, takže se k němu budete moci vrátit.')
                    ->visible(fn (): bool => method_exists($this->resolveRevisionOwner(), 'restoreRevision'))
                    ->action(function (Revision $record): void {
                        $owner = $this->resolveRevisionOwner();

                        if (method_exists($owner, 'restoreRevision')) {
                            $owner->restoreRevision($record->getKey());

                            Notification::make()
                                ->title('Revize byla obnovena')
                                ->body('Záznam „'.$this->resolveRevisionOwnerTitle($owner).'“ byl obnoven podle verze z '.$record->created_at?->format('j. n. Y H:i:s').'.')
                                ->success()
                                ->send();
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
            Section::make('Rozdíly')
                ->schema(function (Get $get): array {
                    $revisionAId = $get('revision_a');
                    $revisionBId = $get('revision_b');

                    if (! $revisionAId || ! $revisionBId) {
                        return [
                            Placeholder::make('comparison_hint')
                                ->hiddenLabel()
                                ->content('Vyberte obě revize k porovnání.'),
                        ];
                    }

                    return $this->buildTwoRevisionDiffSchema((int) $revisionAId, $revisionBId === '__current__' ? null : (int) $revisionBId, prefix: 'compare');
                })
                ->columnSpanFull(),
        ];
    }

    protected function buildTwoRevisionDiffSchema(int $revisionAId, ?int $revisionBId, string $prefix = 'compare'): array
    {
        $owner = $this->resolveRevisionOwner();
        $revisionA = Revision::find($revisionAId);

        if (! $revisionA) {
            return [
                Placeholder::make($prefix.'_missing_revision_a')
                    ->hiddenLabel()
                    ->content('Revize nebyla nalezena.'),
            ];
        }

        $leftData = $revisionA->data ?? [];
        $leftLabel = $revisionA->created_at->format('j. n. Y H:i:s');

        if ($revisionBId === null) {
            $rightData = $this->resolveCurrentOwnerData($owner);
            $rightLabel = 'Aktuální stav';
        } else {
            $revisionB = Revision::find($revisionBId);

            if (! $revisionB) {
                return [
                    Placeholder::make($prefix.'_missing_revision_b')
                        ->hiddenLabel()
                        ->content('Revize nebyla nalezena.'),
                ];
            }

            $rightData = $revisionB->data ?? [];
            $rightLabel = $revisionB->created_at->format('j. n. Y H:i:s');
        }

        return $this->buildComparisonSchema($leftData, $rightData, $leftLabel, $rightLabel, $prefix);
    }

    protected function buildRevisionDiffSchema(Revision $record): array
    {
        $owner = $this->resolveRevisionOwner();
        $leftData = $record->data ?? [];
        $rightData = $this->resolveCurrentOwnerData($owner);

        return $this->buildComparisonSchema(
            $leftData,
            $rightData,
            $record->created_at->format('j. n. Y H:i:s'),
            'Aktuální stav',
            'record_'.$record->getKey(),
        );
    }

    protected function resolveRevisionOwnerTitle(Model $owner): string
    {
        foreach (['title', 'name', 'handle', 'email', 'slug'] as $attribute) {
            $value = $owner->getAttribute($attribute);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '#'.$owner->getKey();
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function buildComparisonSchema(array $leftData, array $rightData, string $leftLabel, string $rightLabel, string $prefix): array
    {
        $payload = $this->revisionDiffPresenter()->buildComparisonPayload($leftData, $rightData, $leftLabel, $rightLabel);

        if (($payload['change_count'] ?? 0) === 0) {
            return [
                Placeholder::make($prefix.'_no_changes')
                    ->hiddenLabel()
                    ->content('Vybrané verze neobsahují žádné rozdíly.'),
            ];
        }

        $components = [
            Section::make('Souhrn')
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'md' => 3,
                    ])->schema([
                        Placeholder::make($prefix.'_summary_count')
                            ->label('Porovnávané položky')
                            ->content((string) ($payload['change_count'] ?? 0)),
                        Placeholder::make($prefix.'_summary_left')
                            ->label('Levá verze')
                            ->content((string) ($payload['left_label'] ?? $leftLabel)),
                        Placeholder::make($prefix.'_summary_right')
                            ->label('Pravá verze')
                            ->content((string) ($payload['right_label'] ?? $rightLabel)),
                    ]),
                ]),
        ];

        foreach (($payload['standard_sections'] ?? []) as $sectionIndex => $section) {
            $sectionName = (string) ($section['name'] ?? 'Rozdíly');
            $changes = is_array($section['changes'] ?? null) ? $section['changes'] : [];

            if ($changes === []) {
                continue;
            }

            $sectionSchema = [];

            foreach ($changes as $changeIndex => $change) {
                $changeField = (string) ($change['field'] ?? 'Pole');
                $changePath = (string) ($change['path'] ?? '');
                $oldValue = (string) ($change['old'] ?? '—');
                $newValue = (string) ($change['new'] ?? '—');

                $sectionSchema[] = Fieldset::make($changeField)
                    ->schema([
                        Placeholder::make($prefix.'_std_'.$sectionIndex.'_'.$changeIndex.'_path')
                            ->label('Klíč')
                            ->content($changePath),
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Placeholder::make($prefix.'_std_'.$sectionIndex.'_'.$changeIndex.'_old')
                                ->label($leftLabel)
                                ->content($oldValue),
                            Placeholder::make($prefix.'_std_'.$sectionIndex.'_'.$changeIndex.'_new')
                                ->label($rightLabel)
                                ->content($newValue),
                        ]),
                    ]);
            }

            $components[] = Section::make($sectionName)
                ->collapsible()
                ->schema($sectionSchema);
        }

        foreach (($payload['mason_sections'] ?? []) as $masonIndex => $masonSection) {
            $masonLabel = (string) ($masonSection['label'] ?? 'Mason obsah');
            $summary = is_array($masonSection['summary'] ?? null) ? $masonSection['summary'] : [];
            $changes = is_array($masonSection['changes'] ?? null) ? $masonSection['changes'] : [];

            if ($changes === []) {
                continue;
            }

            $masonSchema = [
                Grid::make([
                    'default' => 1,
                    'md' => 5,
                ])->schema([
                    Placeholder::make($prefix.'_mason_'.$masonIndex.'_summary_total')
                        ->label('Změny')
                        ->content((string) ($summary['total'] ?? 0)),
                    Placeholder::make($prefix.'_mason_'.$masonIndex.'_summary_added')
                        ->label('Přidáno')
                        ->content((string) ($summary['added'] ?? 0)),
                    Placeholder::make($prefix.'_mason_'.$masonIndex.'_summary_removed')
                        ->label('Odebráno')
                        ->content((string) ($summary['removed'] ?? 0)),
                    Placeholder::make($prefix.'_mason_'.$masonIndex.'_summary_moved')
                        ->label('Přesunuto')
                        ->content((string) ($summary['moved'] ?? 0)),
                    Placeholder::make($prefix.'_mason_'.$masonIndex.'_summary_changed')
                        ->label('Upraveno')
                        ->content((string) ($summary['changed'] ?? 0)),
                ]),
            ];

            foreach ($changes as $changeIndex => $change) {
                $typeLabel = (string) ($change['type_label'] ?? 'Změna');
                $blockType = (string) ($change['block_type'] ?? 'Blok');
                $fieldsetLabel = $typeLabel.' · '.$blockType;

                $masonSchema[] = Fieldset::make($fieldsetLabel)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Placeholder::make($prefix.'_mason_'.$masonIndex.'_'.$changeIndex.'_left')
                                ->label($leftLabel)
                                ->content((string) ($change['left'] ?? 'Žádný blok')),
                            Placeholder::make($prefix.'_mason_'.$masonIndex.'_'.$changeIndex.'_right')
                                ->label($rightLabel)
                                ->content((string) ($change['right'] ?? 'Žádný blok')),
                        ]),
                    ]);
            }

            $components[] = Section::make($masonLabel)
                ->collapsible()
                ->schema($masonSchema);
        }

        return $components;
    }

    protected function resolveRevisionStatus(Revision $record): ?ContentStatus
    {
        $status = data_get($record->data, 'status');

        if ($status instanceof ContentStatus) {
            return $status;
        }

        if (! is_string($status)) {
            return null;
        }

        return ContentStatus::tryFrom(trim($status));
    }

    protected function revisionDiffPresenter(): RevisionDiffPresenter
    {
        return app(RevisionDiffPresenter::class);
    }

    private function resolveCurrentOwnerData(Model $owner): array
    {
        $raw = collect($owner->getOriginal())
            ->except(['id', 'created_at', 'updated_at', 'deleted_at'])
            ->toArray();

        return json_decode((string) json_encode($raw), true) ?? [];
    }
}
