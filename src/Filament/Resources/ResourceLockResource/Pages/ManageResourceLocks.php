<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\ResourceLockResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\LockResource\ManageResourceLocks as BaseManageResourceLocks;
use MiPress\Core\Filament\Resources\ResourceLockResource;

class ManageResourceLocks extends BaseManageResourceLocks
{
    protected static string $resource = ResourceLockResource::class;
}
