<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\PageResource\Schemas\PageSeoForm;

class EditPageSeo extends EditPage
{
    protected static ?string $title = 'SEO';

    protected static ?string $breadcrumb = 'SEO';

    protected static ?string $navigationLabel = 'SEO';

    protected static string|\BackedEnum|null $navigationIcon = 'far-magnifying-glass';

    public function form(Schema $schema): Schema
    {
        return PageSeoForm::configure($schema);
    }
}
