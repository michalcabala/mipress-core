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
        $navigationUrlParameters = $urlParameters;
        $isCurrentPageClass = ($urlParameters['currentPageClass'] ?? null) === static::class;

        unset($navigationUrlParameters['currentPageClass']);

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => $isCurrentPageClass || static::isCurrentSubNavigationRoute())
                ->sort(static::getNavigationSort())
                ->badge(static::getSubNavigationBadge($urlParameters), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->url(static::getNavigationUrl($navigationUrlParameters)),
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
        return original_request()->routeIs(static::getNavigationItemActiveRoutePattern());
    }
}
