<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class ContentCluster extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'fal-layer-group';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $label = null;

    protected static ?string $pluralLabel = null;

    protected static ?int $navigationSort = 40;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('mipress::admin.clusters.content.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.clusters.content.navigation_label');
    }

    public static function getLabel(): string
    {
        return __('mipress::admin.clusters.content.label');
    }

    public static function getPluralLabel(): string
    {
        return __('mipress::admin.clusters.content.plural_label');
    }
}
