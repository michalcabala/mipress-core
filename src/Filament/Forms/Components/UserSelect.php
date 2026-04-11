<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Forms\Components;

use Filament\Forms\Components\Select;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserSelect extends Select
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->getOptionLabelFromRecordUsing(fn (mixed $record): string => UserFieldRenderer::renderOption($record))
            ->allowHtml();
    }
}
