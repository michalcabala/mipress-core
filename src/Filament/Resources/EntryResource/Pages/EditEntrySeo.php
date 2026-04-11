<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\EntryResource\Schemas\EntrySeoForm;

class EditEntrySeo extends EditEntry
{
    protected static ?string $title = 'SEO';

    protected static ?string $breadcrumb = 'SEO';

    protected static ?string $navigationLabel = 'SEO';

    protected static string|\BackedEnum|null $navigationIcon = 'far-magnifying-glass';

    public function form(Schema $schema): Schema
    {
        return EntrySeoForm::configure($schema);
    }
}
