<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use MiPress\Core\Filament\Support\EntryLikeFormBuilders;
use MiPress\Core\Models\Page;

class PageSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        return $schema->components([
            Grid::make(1)
                ->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Page ? EntryLikeFormBuilders::isReadOnlyForCurrentUser($record) : false)
                ->schema([
                    EntryLikeFormBuilders::makeSeoSection(__('mipress::admin.seo_subjects.page'), collapsible: false, columnSpanFull: true),
                ]),
        ]);
    }
}
