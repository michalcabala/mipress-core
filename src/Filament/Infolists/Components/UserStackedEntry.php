<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Infolists\Components;

use Filament\Infolists\Components\ImageEntry;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserStackedEntry extends ImageEntry
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->circular()
            ->stacked()
            ->checkFileExistence(false)
            ->imageHeight(24)
            ->ring(1)
            ->tooltip(fn (mixed $state): ?string => UserFieldRenderer::resolveUserName($state));
    }

    public function getImageUrl($state = null): ?string
    {
        return UserFieldRenderer::resolveAvatarUrl($state, $this->getState());
    }
}
