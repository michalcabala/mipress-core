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
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;
use MiPress\Core\Models\CuratorMedia;

class CuratorMediaResource extends MediaResource
{
    protected static ?string $slug = 'curator-media';

    private const CZECH_MONTHS = [
        1 => 'Leden', 2 => 'Únor', 3 => 'Březen', 4 => 'Duben',
        5 => 'Květen', 6 => 'Červen', 7 => 'Červenec', 8 => 'Srpen',
        9 => 'Září', 10 => 'Říjen', 11 => 'Listopad', 12 => 'Prosinec',
    ];

    public static function form(Schema $schema): Schema
    {
        return CuratorMediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $isGrid = $livewire->layoutView === 'grid';

        return $table
            ->columns(
                $isGrid
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
                    ->options(function (): array {
                        return CuratorMedia::query()
                            ->selectRaw("DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month_key")
                            ->selectRaw('MONTH(created_at) as m, YEAR(created_at) as y')
                            ->orderByDesc('month_key')
                            ->get()
                            ->mapWithKeys(fn (object $row): array => [
                                $row->month_key => self::CZECH_MONTHS[(int) $row->m].' '.$row->y,
                            ])
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! $value) {
                            return $query;
                        }

                        [$year, $month] = explode('-', $value);

                        return $query->whereYear('created_at', $year)
                            ->whereMonth('created_at', $month);
                    }),
                UserSelectFilter::make('uploaded_by')
                    ->label('Nahrál')
                    ->relationship('uploadedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions(
                $isGrid ? [] : [
                    EditAction::make(),
                    DeleteAction::make(),
                ]
            )
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->contentGrid(function () use ($livewire): ?array {
                if ($livewire->layoutView !== 'grid') {
                    return null;
                }

                $density = $livewire->gridDensity ?? 'normal';

                return match ($density) {
                    'compact' => ['sm' => 3, 'md' => 4, 'lg' => 6, 'xl' => 8],
                    default => ['sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 6],
                };
            })
            ->deferLoading()
            ->defaultPaginationPageOption(48)
            ->paginationPageOptions([24, 48, 96])
            ->recordUrl(fn (CuratorMedia $record): string => static::getUrl('edit', ['record' => $record]));
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
                ->imageSize(60),
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
            UserColumn::make('uploaded_by')
                ->label('Nahrál')
                ->getStateUsing(fn (CuratorMedia $record) => $record->uploadedBy)
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
