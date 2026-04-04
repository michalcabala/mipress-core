<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class ContentCluster extends Cluster
{
    protected static string | \BackedEnum | null $navigationIcon = 'fal-layer-group';

    protected static string | \UnitEnum | null $navigationGroup = 'Nastavení';

    protected static ?string $navigationLabel = 'Obsah';

    protected static ?string $label = 'Obsah';

    protected static ?string $pluralLabel = 'Obsah';

    protected static ?int $navigationSort = 40;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;
}
