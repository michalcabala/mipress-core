<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables\Table;
use MiPress\Core\Filament\Concerns\ConfiguresRevisionTable;
use MiPress\Core\Filament\Resources\PageResource;

class PageHistory extends ManageRelatedRecords
{
    use ConfiguresRevisionTable;

    protected static string $resource = PageResource::class;

    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revize';

    protected static ?string $breadcrumb = 'Revize';

    protected static ?string $navigationLabel = 'Revize';

    public function getHeading(): string
    {
        return 'Revize: '.$this->getRecord()->title;
    }

    public function getSubheading(): ?string
    {
        $revisionCount = $this->getRecord()->revisions()->count();

        return $revisionCount === 0
            ? 'Revize se vytvoří při první úpravě obsahu nebo při workflow změně.'
            : 'K dispozici je '.$revisionCount.' uložených verzí. Kliknutím na řádek otevřete detail změn, případně můžete starší verzi obnovit.';
    }

    public function table(Table $table): Table
    {
        return $this->configureRevisionTable($table);
    }
}
