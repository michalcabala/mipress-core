<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Pages;

use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Pages\ListMedia as BaseListMedia;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use MiPress\Core\Services\MediaCurationOrchestrator;

class ListMedia extends BaseListMedia
{
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeaderActions(): array
    {
        $parentActions = parent::getHeaderActions();

        return [
            Action::make('regenerate_all_curations')
                ->label('Přegenerovat vše')
                ->icon('fal-arrows-rotate')
                ->color('gray')
                ->visible(fn (): bool => Filament::auth()->user()?->can('regenerateAllCurations', Media::class) ?? false)
                ->authorize(fn (): bool => Filament::auth()->user()?->can('regenerateAllCurations', Media::class) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat ořezy celé knihovny médií?')
                ->modalDescription('Pro všechny rastrové obrázky v knihovně se znovu vytvoří miniaturní ořezy. U většího objemu bude zpracování pokračovat na pozadí.')
                ->modalSubmitActionLabel('Přegenerovat vše')
                ->action(function (): void {
                    app(MediaCurationOrchestrator::class)
                        ->regenerateAll((int) Filament::auth()->id());
                }),
            ...$parentActions,
        ];
    }
}
