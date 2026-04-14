<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Widgets;

use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Widgets\PublicationStatusOverviewWidget;
use MiPress\Core\Models\Page;

class PagePublicationStatusOverview extends PublicationStatusOverviewWidget
{
    /**
     * @return class-string
     */
    protected function getStatusOverviewModelClass(): string
    {
        return Page::class;
    }

    protected function getStatusOverviewUrl(): string
    {
        return PageResource::getUrl('index');
    }

    protected function getTableQueryStringIdentifier(): string
    {
        return 'pages';
    }
}
