<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CuratorMediaResource\Pages;

use Awcodes\Curator\Resources\Media\Pages\CreateMedia;
use Filament\Actions\Action;
use MiPress\Core\Filament\Resources\CuratorMediaResource;

class CreateCuratorMedia extends CreateMedia
{
    protected static string $resource = CuratorMediaResource::class;

    public function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('mipress::admin.curator_media.actions.cancel'))
                ->color('gray')
                ->url(static::getResource()::getUrl()),
        ];
    }
}
