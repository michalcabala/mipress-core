<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;

class EntryPublicationStatusOverview extends StatsOverviewWidget
{
    public ?int $collectionId = null;

    public ?string $collectionHandle = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected int | array | null $columns = [
        'sm' => 2,
        'xl' => 6,
    ];

    protected ?string $heading = null;

    protected ?string $description = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $statusCounts = Entry::query()
            ->when(
                filled($this->collectionId),
                fn (Builder $query): Builder => $query->where('collection_id', $this->collectionId),
            )
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        $activeStatus = $this->getActiveStatusFilter();
        $allCount = array_sum($statusCounts);

        $stats = [
            $this->makeStat(
                label: 'Vše',
                value: $allCount,
                icon: 'far-layer-group',
                color: 'gray',
                status: null,
                isActive: blank($activeStatus),
            ),
        ];

        foreach (EntryStatus::cases() as $status) {
            $stats[] = $this->makeStat(
                label: $status->getLabel(),
                value: $statusCounts[$status->value] ?? 0,
                icon: $status->getIcon(),
                color: $status->getColor(),
                status: $status,
                isActive: $activeStatus === $status->value,
            );
        }

        return $stats;
    }

    private function makeStat(
        string $label,
        int $value,
        ?string $icon,
        string | array | null $color,
        ?EntryStatus $status,
        bool $isActive,
    ): Stat {
        return Stat::make($label, (string) $value)
            ->icon($icon)
            ->color($color)
            ->url($this->getStatusFilterUrl($status))
            ->extraAttributes([
                'wire:navigate' => true,
                'class' => implode(' ', array_filter([
                    'transition',
                    'hover:bg-gray-50',
                    'dark:hover:bg-white/5',
                    $isActive ? 'ring-2 ring-primary-500/40 bg-primary-50/70 dark:bg-primary-500/10' : null,
                ])),
            ]);
    }

    private function getActiveStatusFilter(): ?string
    {
        $filters = request()->query($this->getTableFiltersQueryStringProperty());

        if (! is_array($filters)) {
            return null;
        }

        $value = data_get($filters, 'status.value');

        return is_string($value) && filled($value)
            ? $value
            : null;
    }

    private function getStatusFilterUrl(?EntryStatus $status): string
    {
        $url = EntryResource::getUrl('index', [
            'collection' => filled($this->collectionHandle) ? $this->collectionHandle : null,
        ]);

        $query = request()->query();
        $filtersProperty = $this->getTableFiltersQueryStringProperty();
        $filters = is_array($query[$filtersProperty] ?? null) ? $query[$filtersProperty] : [];

        unset($query[$this->getTablePaginationQueryStringProperty()], $query['page']);

        if ($status) {
            $filters['status'] = ['value' => $status->value];
        } else {
            unset($filters['status']);
        }

        if ($filters === []) {
            unset($query[$filtersProperty]);
        } else {
            $query[$filtersProperty] = $filters;
        }

        $queryString = http_build_query($query);

        return filled($queryString)
            ? "{$url}?{$queryString}"
            : $url;
    }

    private function getTableFiltersQueryStringProperty(): string
    {
        return $this->getTableQueryStringIdentifier() . 'TableFilters';
    }

    private function getTablePaginationQueryStringProperty(): string
    {
        return $this->getTableQueryStringIdentifier() . 'Page';
    }

    private function getTableQueryStringIdentifier(): string
    {
        $suffix = filled($this->collectionHandle)
            ? str($this->collectionHandle)->studly()->toString()
            : 'Index';

        return 'entries' . $suffix;
    }
}