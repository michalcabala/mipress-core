<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MiPress\Core\Models\Term;

class TermsTable
{
    /**
     * @var array<int, int>
     */
    private static array $termDepthCache = [];

    /**
     * @var array<int, int|null>|null
     */
    private static ?array $termParentMap = null;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Název')
                    ->searchable()
                    ->formatStateUsing(fn (Term $record): string => static::formatHierarchyTitle($record->title, static::getTermDepth($record)))
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label('Nadřazený')
                    ->default('—'),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label('Položek')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function formatHierarchyTitle(string $title, int $depth): string
    {
        if ($depth <= 0) {
            return $title;
        }

        return str_repeat('|  ', $depth).'|- '.$title;
    }

    private static function getTermDepth(Term $record): int
    {
        $recordId = (int) $record->getKey();

        if (array_key_exists($recordId, static::$termDepthCache)) {
            return static::$termDepthCache[$recordId];
        }

        $parentMap = static::getTermParentMap();
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

        static::$termDepthCache[$recordId] = $depth;

        return $depth;
    }

    /**
     * @return array<int, int|null>
     */
    private static function getTermParentMap(): array
    {
        if (static::$termParentMap !== null) {
            return static::$termParentMap;
        }

        static::$termParentMap = Term::query()
            ->select(['id', 'parent_id'])
            ->get()
            ->mapWithKeys(fn (Term $term): array => [(int) $term->getKey() => $term->parent_id ? (int) $term->parent_id : null])
            ->all();

        return static::$termParentMap;
    }
}
