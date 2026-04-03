<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use MiPress\Core\Filament\Concerns\ConfiguresRevisionTable;

class RevisionsRelationManager extends RelationManager
{
    use ConfiguresRevisionTable;

    protected static string $relationship = 'revisions';

    protected static ?string $title = 'Revize';

    protected static string|\BackedEnum|null $icon = 'far-code-compare';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $this->configureRevisionTable($table);
    }
}
