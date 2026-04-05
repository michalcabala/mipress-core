<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class SeoCluster extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'fal-magnifying-glass-chart';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $navigationLabel = 'SEO';

    protected static ?string $label = 'SEO';

    protected static ?string $pluralLabel = 'SEO';

    protected static ?int $navigationSort = 55;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
