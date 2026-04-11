<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Tables\Filters;

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserSelectFilter extends SelectFilter
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->getOptionLabelFromRecordUsing(fn (mixed $record): string => UserFieldRenderer::renderOption($record))
            ->modifyFormFieldUsing(fn (Select $select): Select => $select->allowHtml());
    }
}
