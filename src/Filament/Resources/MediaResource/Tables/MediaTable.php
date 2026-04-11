<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Tables;

use Awcodes\Curator\Models\Media;
use Awcodes\Curator\Resources\Media\Tables\MediaTable as BaseMediaTable;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use MiPress\Core\Services\MediaCurationOrchestrator;

class MediaTable extends BaseMediaTable
{
    public static function configure(Table $table): Table
    {
        $table = parent::configure($table);

        return $table->pushToolbarActions([
            BulkAction::make('regenerate_curations')
                ->label('Přegenerovat ořezy')
                ->icon('fal-arrows-rotate')
                ->color('gray')
                ->visible(fn (): bool => Filament::auth()->user()?->can('regenerateSelectedCurations', Media::class) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat ořezy vybraných souborů?')
                ->modalDescription('Pro vybrané rastrové obrázky se znovu vytvoří miniaturní ořezy a původní varianty se přepíšou.')
                ->modalSubmitActionLabel('Přegenerovat')
                ->action(function (Collection $records): void {
                    app(MediaCurationOrchestrator::class)
                        ->regenerateSelected($records, (int) Filament::auth()->id());
                }),
        ]);
    }
}
