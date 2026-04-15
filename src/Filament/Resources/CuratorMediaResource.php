<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Awcodes\Curator\Components\Tables\CuratorColumn;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Resources\Media\MediaResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
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

    public static function form(Schema $schema): Schema
    {
        return CuratorMediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();
        $isGrid = $livewire->layoutView === 'grid';
        $months = __('mipress::admin.curator_media.months');

        return $table
            ->columns(
                $isGrid
                    ? static::getGridColumns()
                    : static::getListColumns(),
            )
            ->searchable(['name', 'title', 'alt', 'caption', 'description'])
            ->filters([
                SelectFilter::make('media_type')
                    ->label(__('mipress::admin.curator_media.filters.media_type'))
                    ->options([
                        'image' => __('mipress::admin.curator_media.media_types.image'),
                        'video' => __('mipress::admin.curator_media.media_types.video'),
                        'audio' => __('mipress::admin.curator_media.media_types.audio'),
                        'document' => __('mipress::admin.curator_media.media_types.document'),
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
                    ->label(__('mipress::admin.curator_media.filters.upload_month'))
                    ->options(function () use ($months): array {
                        return CuratorMedia::query()
                            ->selectRaw("DISTINCT DATE_FORMAT(created_at, '%Y-%m') as month_key")
                            ->selectRaw('MONTH(created_at) as m, YEAR(created_at) as y')
                            ->orderByDesc('month_key')
                            ->get()
                            ->mapWithKeys(fn (object $row): array => [
                                $row->month_key => (is_array($months) ? ($months[(int) $row->m] ?? $row->m) : $row->m).' '.$row->y,
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
                    ->label(__('mipress::admin.curator_media.filters.uploaded_by'))
                    ->relationship('uploadedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions(
                $isGrid ? [] : [
                    ActionGroup::make([
                        EditAction::make(),
                        DeleteAction::make(),
                    ]),
                ]
            )
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
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
                ->label(__('mipress::admin.curator_media.columns.preview'))
                ->imageSize(60),
            TextColumn::make('name')
                ->label(__('mipress::admin.curator_media.columns.name'))
                ->searchable()
                ->sortable()
                ->description(fn (CuratorMedia $record): ?string => $record->alt),
            TextColumn::make('media_type_label')
                ->label(__('mipress::admin.curator_media.columns.type'))
                ->badge()
                ->color(fn (CuratorMedia $record): string => match (true) {
                    curator()->isPreviewable($record->ext) => 'success',
                    curator()->isVideo($record->ext) => 'info',
                    curator()->isAudio($record->ext) => 'warning',
                    default => 'gray',
                }),
            TextColumn::make('ext')
                ->label(__('mipress::admin.curator_media.columns.ext'))
                ->sortable(),
            TextColumn::make('size')
                ->label(__('mipress::admin.curator_media.columns.size'))
                ->formatStateUsing(fn (CuratorMedia $record): string => Curator::sizeForHumans($record->size))
                ->sortable(),
            TextColumn::make('dimensions')
                ->label(__('mipress::admin.curator_media.columns.dimensions'))
                ->getStateUsing(fn (CuratorMedia $record): ?string => $record->width ? $record->width.'×'.$record->height : null),
            UserColumn::make('uploaded_by')
                ->label(__('mipress::admin.curator_media.columns.uploaded_by'))
                ->getStateUsing(fn (CuratorMedia $record) => $record->uploadedBy)
                ->toggleable()
                ->toggledHiddenByDefault(),
            TextColumn::make('created_at')
                ->label(__('mipress::admin.curator_media.columns.uploaded_at'))
                ->date('j. n. Y')
                ->sortable(),
        ];
    }

    private static function getGridColumns(): array
    {
        return [
            View::make('mipress::curator.grid-column'),
            TextColumn::make('name')
                ->label(__('mipress::admin.curator_media.columns.name'))
                ->extraAttributes(['style' => 'display: none;'])
                ->searchable()
                ->sortable(),
            TextColumn::make('ext')
                ->label(__('mipress::admin.curator_media.columns.ext'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('directory')
                ->label(__('mipress::admin.curator_media.columns.directory'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
            TextColumn::make('created_at')
                ->label(__('mipress::admin.curator_media.columns.uploaded_at'))
                ->extraAttributes(['style' => 'display: none;'])
                ->sortable(),
        ];
    }
}
