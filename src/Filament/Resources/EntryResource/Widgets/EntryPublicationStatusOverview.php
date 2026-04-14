<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;

class EntryPublicationStatusOverview extends StatsOverviewWidget
{
    public ?int $collectionId = null;

    public ?string $collectionHandle = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected int | array | null $columns = [
        'md' => 3,
        'xl' => 6,
    ];

    protected ?string $heading = null;

    protected ?string $description = null;

    #[On('entry-publication-status-updated')]
    public function refreshStatusOverview(): void
    {
    }

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

        $trashedCount = Entry::onlyTrashed()
            ->when(
                filled($this->collectionId),
                fn (Builder $query): Builder => $query->where('collection_id', $this->collectionId),
            )
            ->count();

        $activeFilter = $this->getActiveFilter();
        $allCount = array_sum($statusCounts);

        $stats = [
            $this->makeStat(
                key: 'all',
                label: 'Vše',
                count: $allCount,
                icon: 'far-layer-group',
                tone: 'gray',
                isActive: $activeFilter === 'all',
            ),
        ];

        foreach (EntryStatus::cases() as $status) {
            $count = $statusCounts[$status->value] ?? 0;

            if ($count < 1) {
                continue;
            }

            $stats[] = $this->makeStat(
                key: $status->value,
                label: $status->getLabel(),
                count: $count,
                icon: $status->getIcon(),
                tone: (string) $status->getColor(),
                isActive: $activeFilter === $status->value,
            );
        }

        if ($trashedCount > 0) {
            $stats[] = $this->makeStat(
                key: 'trashed',
                label: 'Koš',
                count: $trashedCount,
                icon: 'far-trash-can',
                tone: 'danger',
                isActive: $activeFilter === 'trashed',
            );
        }

        return $stats;
    }

    /**
     * @return Stat
     */
    private function makeStat(
        string $key,
        string $label,
        int $count,
        ?string $icon,
        string $tone,
        bool $isActive,
    ): Stat {
        return Stat::make($label, (string) $count)
            ->icon($icon)
            ->color($tone)
            ->url($this->getFilterUrl($key))
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

    private function getActiveFilter(): string
    {
        $filters = request()->query($this->getTableFiltersQueryStringProperty());

        if (! is_array($filters)) {
            return 'all';
        }

        $trashed = data_get($filters, 'trashed.value');

        if (($trashed === false) || ($trashed === 'false')) {
            return 'trashed';
        }

        $value = data_get($filters, 'status.value');

        return is_string($value) && filled($value)
            ? $value
            : 'all';
    }

    private function getFilterUrl(string $key): string
    {
        $url = EntryResource::getUrl('index', [
            'collection' => filled($this->collectionHandle) ? $this->collectionHandle : null,
        ]);

        $query = request()->query();
        $filtersProperty = $this->getTableFiltersQueryStringProperty();
        $filters = is_array($query[$filtersProperty] ?? null) ? $query[$filtersProperty] : [];

        unset($query[$this->getTablePaginationQueryStringProperty()], $query['page']);

        unset($filters['status'], $filters['trashed']);

        if ($key === 'trashed') {
            $filters['trashed'] = ['value' => 'false'];
        } elseif ($key !== 'all') {
            $filters['status'] = ['value' => $key];
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
