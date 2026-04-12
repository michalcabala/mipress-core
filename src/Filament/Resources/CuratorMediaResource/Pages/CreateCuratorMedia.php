<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CuratorMediaResource\Pages;

use Awcodes\Curator\Resources\Media\Pages\CreateMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource;

class CreateCuratorMedia extends CreateMedia
{
    protected static string $resource = CuratorMediaResource::class;
}
