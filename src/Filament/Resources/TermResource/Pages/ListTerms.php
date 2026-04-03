<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use MiPress\Core\Filament\Resources\TermResource;

class ListTerms extends ListRecords
{
    protected static string $resource = TermResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create', [
                    'taxonomy_id' => request()->query('taxonomy_id'),
                ])),
        ];
    }

    public function getTitle(): string
    {
        $taxonomyId = request()->query('taxonomy_id');
        if ($taxonomyId) {
            $taxonomy = \MiPress\Core\Models\Taxonomy::find($taxonomyId);
            if ($taxonomy) {
                return $taxonomy->title;
            }
        }

        return 'Štítky';
    }
}
