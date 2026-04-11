<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Tables\Columns;

use Filament\Tables\Columns\ImageColumn;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserStackedColumn extends ImageColumn
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
