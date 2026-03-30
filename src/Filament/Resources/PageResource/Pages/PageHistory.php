<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ManageRelatedRecords;
use MiPress\Core\Filament\Resources\PageResource;

class PageHistory extends ManageRelatedRecords
{
    protected static string $resource = PageResource::class;

    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Historie změn';

    protected static ?string $breadcrumb = 'Historie';

    public function getHeading(): string
    {
        return 'Historie: ' . $this->getRecord()->title;
    }
}
