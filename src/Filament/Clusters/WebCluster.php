<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class WebCluster extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'fal-globe';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $navigationLabel = 'Web';

    protected static ?string $label = 'Web';

    protected static ?string $pluralLabel = 'Web';

    protected static ?int $navigationSort = 50;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
