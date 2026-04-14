<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
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

    #[On('entry-publication-status-updated')]
    public function refreshStatusOverview(): void
    {
    }

    /**
     * @return array<string, array<int, array{key: string, label: string, count: int, icon: ?string, color: string, isActive: bool, url: string}>>
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
                color: 'gray',
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
                color: (string) $status->getColor(),
                isActive: $activeFilter === $status->value,
            );
        }

        if ($trashedCount > 0) {
            $items[] = $this->makeItem(
                key: 'trashed',
                label: 'Koš',
                count: $trashedCount,
                icon: 'far-trash-can',
                color: 'danger',
                isActive: $activeFilter === 'trashed',
            );
        }

        return [
            'items' => $items,
        ];
    }

    /**
     * @return array{key: string, label: string, count: int, icon: ?string, color: string, isActive: bool, url: string}
     */
    private function makeItem(
        string $key,
        string $label,
        int $count,
        ?string $icon,
        string $color,
        bool $isActive,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'count' => $count,
            'icon' => $icon,
            'color' => $color,
            'isActive' => $isActive,
            'url' => $this->getFilterUrl($key),
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
