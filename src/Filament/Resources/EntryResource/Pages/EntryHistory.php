<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRelatedRecords;
use MiPress\Core\Filament\Resources\EntryResource;

class EntryHistory extends ManageRelatedRecords
{
    protected static string $resource = EntryResource::class;

    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'Historie změn';

    protected static ?string $breadcrumb = 'Historie';

    public function getHeading(): string
    {
        return 'Historie: '.$this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revisions')
                ->label('Revize')
                ->icon('far-code-compare')
                ->url(EntryResource::getUrl('revisions', ['record' => $this->getRecord()])),
        ];
    }
}
