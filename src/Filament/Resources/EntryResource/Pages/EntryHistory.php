<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use MiPress\Core\Filament\Concerns\ConfiguresRevisionTable;
use MiPress\Core\Filament\Resources\EntryResource;

class EntryHistory extends ManageRelatedRecords
{
    use ConfiguresRevisionTable;

    protected static string $resource = EntryResource::class;

    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revize';

    protected static ?string $breadcrumb = 'Revize';

    public function getHeading(): string
    {
        return 'Revize: '.$this->getRecord()->title;
    }

    public function table(Table $table): Table
    {
        return $this->configureRevisionTable($table);
    }
}
