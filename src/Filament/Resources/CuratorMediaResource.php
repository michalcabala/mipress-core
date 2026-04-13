<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Awcodes\Curator\Components\Tables\CuratorColumn;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Resources\Media\MediaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\CreateCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\EditCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Pages\ListCuratorMedia;
use MiPress\Core\Filament\Resources\CuratorMediaResource\Schemas\CuratorMediaForm;
use MiPress\Core\Models\CuratorMedia;

class CuratorMediaResource extends MediaResource
{
    protected static ?string $slug = 'curator-media';

    public static function form(Schema $schema): Schema
    {
        return CuratorMediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return $table
            ->columns(
                $livewire->layoutView === 'grid'
                    ? static::getGridColumns()
                    : static::getListColumns(),
            )
            ->searchable(['name', 'title', 'alt', 'caption', 'description'])
            ->filters([
                SelectFilter::make('media_type')
                    ->label('Typ souboru')
                    ->options([
                        'image' => 'Obrázek',
                        'video' => 'Video',
                        'audio' => 'Audio',
                        'document' => 'Dokument',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        return match ($value) {
                            'image' => $query->whereIn('ext', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'ico']),
                            'video' => $query->whereIn('ext', ['mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv']),
                            'audio' => $query->whereIn('ext', ['mp3', 'wav', 'ogg', 'flac', 'aac', 'wma']),
                            'document' => $query->whereNotIn('ext', [
                                'jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'ico',
                                'mp4', 'webm', 'mov', 'avi', 'mkv', 'wmv',
                                'mp3', 'wav', 'ogg', 'flac', 'aac', 'wma',
                            ]),
                            default => $query,
                        };
                    }),
                SelectFilter::make('upload_month')
                    ->label('Měsíc nahrání')
                    ->options(fn (): array => CuratorMedia::query()
                        ->selectRaw("DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month_key")
                        ->selectRaw("DATE_FORMAT(created_at, '%m/%Y') as month_label")
                        ->orderByDesc('month_key')
                        ->pluck('month_label', 'month_key')
                        ->all()
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        [$year, $month] = explode('-', $value);

                        return $query->whereYear('created_at', $year)
                            ->whereMonth('created_at', $month);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->contentGrid(function () use ($livewire): ?array {
                if ($livewire->layoutView === 'grid') {
                    return [
                        'md' => 2,
                        'lg' => 3,
                        'xl' => 4,
                    ];
                }

                return null;
            })
            ->deferLoading()
            ->defaultPaginationPageOption(24)
            ->paginationPageOptions([12, 24, 48, 'all'])
            ->recordUrl(null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCuratorMedia::route('/'),
            'create' => CreateCuratorMedia::route('/create'),
            'edit' => EditCuratorMedia::route('/{record}/edit'),
        ];
    }

    private static function getListColumns(): array
    {
        return [
            CuratorColumn::make('url')
                ->label('Náhled')
                ->imageSize(40),
            TextColumn::make('name')
                ->label('Název')
                ->searchable()
                ->sortable()
                ->description(fn (CuratorMedia $record): ?string => $record->alt),
            TextColumn::make('media_type_label')
                ->label('Typ')
                ->badge()
                ->color(fn (CuratorMedia $record): string => match (true) {
                    curator()->isPreviewable($record->ext) => 'success',
                    curator()->isVideo($record->ext) => 'info',
                    curator()->isAudio($record->ext) => 'warning',
                    default => 'gray',
                }),
            TextColumn::make('ext')
                ->label('Přípona')
                ->sortable(),
            TextColumn::make('size')
                ->label('Velikost')
                ->formatStateUsing(fn (CuratorMedia $record): string => Curator::sizeForHumans($record->size))
                ->sortable(),
            TextColumn::make('dimensions')
                ->label('Rozměry')
                ->getStateUsing(fn (CuratorMedia $record): ?string => $record->width ? $record->width.'×'.$record->height : null),
            TextColumn::make('uploadedBy.name')
                ->label('Nahrál')
                ->toggleable()
                ->toggledHiddenByDefault(),
            TextColumn::make('created_at')
                ->label('Nahráno')
                ->date('j. n. Y')
                ->sortable(),
        ];
    }

    private static function getGridColumns(): array
    {
        return [
            View::make('mipress::curator.grid-column'),
            TextColumn::make('name')
                ->label('Název')
                ->extraAttributes(['style' => 'display: none;'])
                ->searchable()
                ->sortable(),
            TextColumn::make('ext')
                ->label('Přípona')
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('directory')
                ->label('Složka')
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Nahráno')
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
        ];
    }
}
