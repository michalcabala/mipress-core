<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;

trait HasRecordStateTabs
{
    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Celkem')
                ->badge(fn (): int => $this->getRecordStateTabBadgeQuery()->count())
                ->badgeColor('gray')
                ->deferBadge()
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyVisibleRecordsConstraint($query)),
        ];

        foreach (EntryStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge(fn (): int => $this->getRecordStateTabBadgeQuery($status)->count())
                ->badgeColor($status->getColor())
                ->deferBadge()
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyStatusConstraint($query, $status));
        }

        $tabs['trashed'] = Tab::make('Koš')
            ->badge(fn (): int => $this->getTrashedRecordStateTabBadgeQuery()->count())
            ->badgeColor('danger')
            ->deferBadge()
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->applyTrashedRecordsConstraint($query));

        return $tabs;
    }

    abstract protected function getRecordStateTabsBaseQuery(): Builder;

    private function getRecordStateTabBadgeQuery(?EntryStatus $status = null): Builder
    {
        $query = $this->applyVisibleRecordsConstraint(clone $this->getRecordStateTabsBaseQuery());

        if ($status instanceof EntryStatus) {
            $query->where('status', $status->value);
        }

        return $query;
    }

    private function getTrashedRecordStateTabBadgeQuery(): Builder
    {
        return $this->applyTrashedRecordsConstraint(clone $this->getRecordStateTabsBaseQuery());
    }

    private function applyStatusConstraint(Builder $query, EntryStatus $status): Builder
    {
        return $this->applyVisibleRecordsConstraint($query)
            ->where('status', $status->value);
    }

    private function applyVisibleRecordsConstraint(Builder $query): Builder
    {
        return $query->whereNull($query->getModel()->getQualifiedDeletedAtColumn());
    }

    private function applyTrashedRecordsConstraint(Builder $query): Builder
    {
        return $query->whereNotNull($query->getModel()->getQualifiedDeletedAtColumn());
    }
}
