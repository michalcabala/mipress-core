<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Infolists\Components;

use Filament\Infolists\Components\TextEntry;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserEntry extends TextEntry
{
    protected bool $isWrapped = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->html()
            ->formatStateUsing(fn (mixed $state): string => UserFieldRenderer::renderState($state));
    }

    public function wrapped(bool $isWrapped = true): static
    {
        $this->isWrapped = $isWrapped;

        return $this;
    }

    public function getExtraAttributes(): array
    {
        $attributes = parent::getExtraAttributes();
        $existingClasses = (string) ($attributes['class'] ?? '');
        $layoutClasses = 'flex flex-wrap gap-y-1 gap-x-2';

        if ($this->isWrapped) {
            $layoutClasses .= ' flex-col';
        }

        $attributes['class'] = trim($existingClasses.' '.$layoutClasses);

        return $attributes;
    }
}
