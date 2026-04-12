<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Awcodes\Curator\Resources\Media\MediaResource;
use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\CreateCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\EditCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\ListCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Schemas\CuratorMediaForm;

class CuratorMediaResource extends MediaResource
{
    protected static ?string $slug = 'curator-media';

    public static function form(Schema $schema): Schema
    {
        return CuratorMediaForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCuratorMedia::route('/'),
            'create' => CreateCuratorMedia::route('/create'),
            'edit' => EditCuratorMedia::route('/{record}/edit'),
        ];
    }
}
