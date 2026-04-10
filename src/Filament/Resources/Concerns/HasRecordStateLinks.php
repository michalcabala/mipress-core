<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Enums\EntryStatus;

trait HasRecordStateLinks
{
    /**
     * @return array<int, array{label: string, count: int, url: string, active: bool, icon: string, color: string, iconClass: string}>
     */
    public function getRecordStateLinks(): array
    {
        $baseQuery = $this->getRecordStateLinksQuery();

        $countRows = (clone $baseQuery)
            ->toBase()
            ->selectRaw('status, (deleted_at IS NULL) as is_visible, COUNT(*) as aggregate')
            ->groupBy('status')
            ->groupByRaw('(deleted_at IS NULL)')
            ->get();

        $visibleStatusCounts = $countRows
            ->filter(fn (object $row): bool => (int) $row->is_visible === 1)
            ->mapWithKeys(fn (object $row): array => [(string) $row->status => (int) $row->aggregate])
            ->all();

        $visibleTotal = array_sum($visibleStatusCounts);

        $trashedCount = (int) $countRows
            ->reject(fn (object $row): bool => (int) $row->is_visible === 1)
            ->sum(fn (object $row): int => (int) $row->aggregate);

        $links = [
            $this->makeRecordStateLink(
                label: 'Celkem',
                count: $visibleTotal,
                url: $this->getRecordStateLinkUrl(),
                active: $this->isRecordStateLinkActive(),
                icon: 'far-layer-group',
                color: 'gray',
            ),
        ];

        foreach (EntryStatus::cases() as $status) {
            $count = $visibleStatusCounts[$status->value] ?? 0;

            if ($count < 1) {
                continue;
            }

            $links[] = $this->makeRecordStateLink(
                label: $status->getLabel(),
                count: $count,
                url: $this->getRecordStateLinkUrl(status: $status),
                active: $this->isRecordStateLinkActive(status: $status),
                icon: $status->getIcon() ?? 'far-circle',
                color: (string) ($status->getColor() ?? 'gray'),
            );
        }

        if ($trashedCount > 0) {
            $links[] = $this->makeRecordStateLink(
                label: 'Koš',
                count: $trashedCount,
                url: $this->getRecordStateLinkUrl(trashed: true),
                active: $this->isRecordStateLinkActive(trashed: true),
                icon: 'far-trash-can',
                color: 'danger',
            );
        }

        return $links;
    }

    abstract protected function getRecordStateLinksQuery(): Builder;

    /**
     * @return array<string, mixed>
     */
    protected function getRecordStateLinksRouteParameters(): array
    {
        return [];
    }

    private function getRecordStateLinkUrl(?EntryStatus $status = null, bool $trashed = false): string
    {
        $parameters = $this->getRecordStateLinksRouteParameters();

        if ($status instanceof EntryStatus) {
            $parameters['filters'] = [
                'status' => ['value' => $status->value],
            ];
        }

        if ($trashed) {
            $parameters['filters'] = [
                'trashed' => ['value' => 0],
            ];
        }

        return static::getResource()::getUrl('index', $parameters);
    }

    private function isRecordStateLinkActive(?EntryStatus $status = null, bool $trashed = false): bool
    {
        $currentStatus = data_get($this->tableFilters ?? [], 'status.value');

        if ($currentStatus instanceof EntryStatus) {
            $currentStatus = $currentStatus->value;
        }

        $currentTrashed = data_get($this->tableFilters ?? [], 'trashed.value');

        if ($trashed) {
            return in_array($currentTrashed, [0, '0', false], true);
        }

        if ($status instanceof EntryStatus) {
            return $currentStatus === $status->value && blank($currentTrashed);
        }

        return blank($currentStatus) && blank($currentTrashed);
    }

    /**
     * @return array{label: string, count: int, url: string, active: bool, icon: string, color: string, iconClass: string}
     */
    private function makeRecordStateLink(string $label, int $count, string $url, bool $active, string $icon, string $color): array
    {
        return [
            'label' => $label,
            'count' => $count,
            'url' => $url,
            'active' => $active,
            'icon' => $icon,
            'color' => $color,
            'iconClass' => $this->getRecordStateLinkIconClass($color),
        ];
    }

    private function getRecordStateLinkIconClass(string $color): string
    {
        return match ($color) {
            'success' => 'text-green-600 dark:text-green-400',
            'warning' => 'text-amber-600 dark:text-amber-400',
            'info' => 'text-sky-600 dark:text-sky-400',
            'danger' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }
}
