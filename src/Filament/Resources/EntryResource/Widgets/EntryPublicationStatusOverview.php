<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;

class EntryPublicationStatusOverview extends Widget
{
    public ?int $collectionId = null;

    public ?string $collectionHandle = null;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'mipress::filament.widgets.entry-publication-status-overview';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
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

        $items = [
            $this->makeItem(
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

            $items[] = $this->makeItem(
                key: $status->value,
                label: $status->getLabel(),
                count: $count,
                icon: $status->getIcon(),
                tone: (string) $status->getColor(),
                isActive: $activeFilter === $status->value,
            );
        }

        if ($trashedCount > 0) {
            $items[] = $this->makeItem(
                key: 'trashed',
                label: 'Koš',
                count: $trashedCount,
                icon: 'far-trash-can',
                tone: 'danger',
                isActive: $activeFilter === 'trashed',
            );
        }

        return [
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeItem(
        string $key,
        string $label,
        int $count,
        ?string $icon,
        string $tone,
        bool $isActive,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'icon' => $icon,
            'url' => $this->getFilterUrl($key),
            'isActive' => $isActive,
            'itemClass' => $this->getItemClass($tone, $isActive),
            'countClass' => $this->getCountClass($tone, $isActive),
        ];
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

    private function getItemClass(string $tone, bool $isActive): string
    {
        $baseClass = 'group flex shrink-0 items-center gap-2 rounded-lg border px-3 py-2 text-xs font-medium whitespace-nowrap transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50';

        if (! $isActive) {
            return $baseClass . ' border-gray-200 bg-white text-gray-700 hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:border-white/20 dark:hover:bg-white/10';
        }

        return $baseClass . ' ' . match ($tone) {
            'success' => 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
            'warning' => 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
            'info' => 'border-cyan-300 bg-cyan-50 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/10 dark:text-cyan-200',
            'danger' => 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200',
            default => 'border-gray-300 bg-gray-100 text-gray-800 dark:border-white/20 dark:bg-white/10 dark:text-white',
        };
    }

    private function getCountClass(string $tone, bool $isActive): string
    {
        $baseClass = 'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold';

        if (! $isActive) {
            return $baseClass . ' bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200';
        }

        return $baseClass . ' ' . match ($tone) {
            'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200',
            'info' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/20 dark:text-cyan-200',
            'danger' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
            default => 'bg-white/80 text-gray-700 dark:bg-white/10 dark:text-white',
        };
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
