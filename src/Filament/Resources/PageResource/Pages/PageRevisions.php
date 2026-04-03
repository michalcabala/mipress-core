<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ManageRelatedRecords;
use MiPress\Core\Filament\Resources\PageResource;

class PageRevisions extends ManageRelatedRecords
{
    protected static string $resource = PageResource::class;

    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revize';

    protected static ?string $breadcrumb = 'Revize';

    public function getHeading(): string
    {
        return 'Revize: '.$this->getRecord()->title;
    }
}
