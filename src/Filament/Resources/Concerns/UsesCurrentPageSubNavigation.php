<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Navigation\NavigationItem;

use function Filament\Support\original_request;

trait UsesCurrentPageSubNavigation
{
    /**
     * @param  array<string, mixed>  $urlParameters
     * @return array<int, NavigationItem>
     */
    public static function getNavigationItems(array $urlParameters = []): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => static::isCurrentSubNavigationRoute())
                ->sort(static::getNavigationSort())
                ->badge(static::getSubNavigationBadge($urlParameters), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->url(static::getNavigationUrl($urlParameters)),
        ];
    }

    /**
     * @param  array<string, mixed>  $urlParameters
     */
    protected static function getSubNavigationBadge(array $urlParameters = []): ?string
    {
        return static::getNavigationBadge();
    }

    protected static function isCurrentSubNavigationRoute(): bool
    {
        $currentRequest = request();
        $currentRouteName = $currentRequest->route()?->getName();

        if (is_string($currentRouteName) && ! str_starts_with($currentRouteName, 'livewire.')) {
            return $currentRequest->routeIs(static::getNavigationItemActiveRoutePattern());
        }

        return original_request()->routeIs(static::getNavigationItemActiveRoutePattern());
    }
}
