<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Blendbyte\FilamentResourceLock\Resources\LockResource as BaseLockResource;
use MiPress\Core\Filament\Clusters\ContentCluster;
use MiPress\Core\Filament\Resources\ResourceLockResource\Pages\ManageResourceLocks;

class ResourceLockResource extends BaseLockResource
{
    protected static ?string $cluster = ContentCluster::class;

    protected static ?string $slug = 'zaznamove-zamky';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return 'Správa zámků';
    }

    public static function getPluralLabel(): string
    {
        return 'Zámky';
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResourceLocks::route('/'),
        ];
    }
}