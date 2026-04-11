<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;

trait HasRecordStateTabs
{
    /**
     * @return array<string|int|null, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getRecordStateTabCounts();

        $tabs = [
            null => Tab::make('Celkem')
                ->icon('far-layer-group')
                ->badge(fn (): int => $counts['visibleTotal'])
                ->badgeColor('gray')
                ->deferBadge(),
        ];

        foreach (EntryStatus::cases() as $status) {
            $count = $counts['visibleStatusCounts'][$status->value] ?? 0;

            if ($count < 1) {
                continue;
            }

            $tabs[$status->value] = Tab::make($status->getLabel())
                ->icon($status->getIcon())
                ->badge(fn (): int => $count)
                ->badgeColor($status->getColor())
                ->deferBadge()
                ->query(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        if ($counts['trashedTotal'] > 0) {
            $tabs['trashed'] = Tab::make('Koš')
                ->icon('far-trash-can')
                ->badge(fn (): int => $counts['trashedTotal'])
                ->badgeColor('danger')
                ->deferBadge()
                ->query(fn (Builder $query): Builder => $query->onlyTrashed());
        }

        return $tabs;
    }

    abstract protected function getRecordStateTabsBaseQuery(): Builder;

    /**
     * @return array{visibleTotal: int, visibleStatusCounts: array<string, int>, trashedTotal: int}
     */
    private function getRecordStateTabCounts(): array
    {
        $countRows = (clone $this->getRecordStateTabsBaseQuery())
            ->toBase()
            ->selectRaw('status, (deleted_at IS NULL) as is_visible, COUNT(*) as aggregate')
            ->groupBy('status')
            ->groupByRaw('(deleted_at IS NULL)')
            ->get();

        $visibleStatusCounts = $countRows
            ->filter(fn (object $row): bool => (int) $row->is_visible === 1)
            ->mapWithKeys(fn (object $row): array => [(string) $row->status => (int) $row->aggregate])
            ->all();

        return [
            'visibleTotal' => array_sum($visibleStatusCounts),
            'visibleStatusCounts' => $visibleStatusCounts,
            'trashedTotal' => (int) $countRows
                ->reject(fn (object $row): bool => (int) $row->is_visible === 1)
                ->sum(fn (object $row): int => (int) $row->aggregate),
        ];
    }
}
