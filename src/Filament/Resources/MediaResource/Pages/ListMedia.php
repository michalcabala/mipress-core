<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use MiPress\Core\Enums\MediaType;
use MiPress\Core\Filament\Resources\MediaResource;
use MiPress\Core\Models\Media;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    #[Url(as: 'view')]
    public string $viewMode = 'grid';

    #[Url(as: 'type')]
    public string $typeFilter = '';

    public function table(Table $table): Table
    {
        $isGrid = $this->viewMode === 'grid';

        $gridColumns = [
            Stack::make([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->getStateUsing(fn (Media $record): string => $record->getThumbnailUrl())
                    ->height(130)
                    ->width('100%')
                    ->extraImgAttributes(['class' => 'object-cover w-full h-full rounded-t-lg'])
                    ->defaultImageUrl(fn (Media $record): string => $this->getFileIconUrl($record))
                    ->hidden(fn (): bool => ! $isGrid),

                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->weight('medium')
                    ->limit(25)
                    ->hidden(fn (): bool => ! $isGrid),

                TextColumn::make('mime_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (Media $record): string => $record->getMediaType()->getLabel())
                    ->color(fn (Media $record): string => $record->getMediaType()->getColor() ?? 'gray')
                    ->size('sm')
                    ->hidden(fn (): bool => ! $isGrid),
            ])->space(1),
        ];

        $listColumns = [
            ImageColumn::make('thumbnail_list')
                ->label('')
                ->getStateUsing(fn (Media $record): string => $record->getThumbnailUrl())
                ->height(48)
                ->width(64)
                ->extraImgAttributes(['class' => 'object-cover'])
                ->hidden(fn (): bool => $isGrid),

            TextColumn::make('name')
                ->label('Název')
                ->searchable()
                ->sortable()
                ->hidden(fn (): bool => $isGrid),

            TextColumn::make('mime_type')
                ->label('Typ')
                ->formatStateUsing(fn (Media $record): string => $record->getMediaType()->getLabel())
                ->badge()
                ->hidden(fn (): bool => $isGrid),

            TextColumn::make('size')
                ->label('Velikost')
                ->formatStateUsing(fn (Media $record): string => $record->getHumanReadableSize())
                ->sortable()
                ->hidden(fn (): bool => $isGrid),

            TextColumn::make('created_at')
                ->label('Nahráno')
                ->dateTime('j. n. Y H:i')
                ->sortable()
                ->hidden(fn (): bool => $isGrid),
        ];

        $columns = $isGrid ? $gridColumns : $listColumns;

        return parent::table($table)
            ->columns($columns)
            ->when($isGrid, fn (Table $t) => $t->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
                'xl' => 5,
            ]))
            ->modifyQueryUsing(function (Builder $query): Builder {
                if ($this->typeFilter) {
                    $type = MediaType::from($this->typeFilter);

                    $query->where('mime_type', 'LIKE', match ($type) {
                        MediaType::Image => 'image/%',
                        MediaType::Video => 'video/%',
                        MediaType::Audio => 'audio/%',
                        default => '%',
                    });

                    if (in_array($type, [MediaType::Document, MediaType::Archive, MediaType::Other], true)) {
                        $query->whereNotIn(
                            'mime_type',
                            collect(['image/', 'video/', 'audio/'])
                                ->map(fn ($prefix) => $prefix.'%')
                                ->toArray()
                        );
                    }
                }

                return $query;
            })
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ souboru')
                    ->options(MediaType::class)
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return match (MediaType::from($data['value'])) {
                            MediaType::Image => $query->where('mime_type', 'LIKE', 'image/%'),
                            MediaType::Video => $query->where('mime_type', 'LIKE', 'video/%'),
                            MediaType::Audio => $query->where('mime_type', 'LIKE', 'audio/%'),
                            MediaType::Document => $query->whereIn('mime_type', [
                                'application/pdf', 'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/plain', 'text/csv',
                            ]),
                            MediaType::Archive => $query->whereIn('mime_type', [
                                'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                            ]),
                            default => $query,
                        };
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Nahrát média')
                ->icon('far-upload')
                ->modalHeading('Nahrát nová média')
                ->modalWidth(Width::Large)
                ->form([
                    FileUpload::make('files')
                        ->label('Soubory')
                        ->multiple()
                        ->maxSize(51200)
                        ->acceptedFileTypes([
                            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                            'application/pdf',
                            'video/mp4', 'video/webm',
                            'audio/mpeg', 'audio/wav', 'audio/ogg',
                            'application/zip',
                        ])
                        ->disk('public')
                        ->directory('media-temp')
                        ->visibility('public')
                        ->image()
                        ->imageEditor()
                        ->required(),

                    TextInput::make('collection')
                        ->label('Kolekce (volitelné)')
                        ->placeholder('default'),
                ])
                ->action(function (array $data): void {
                    $collection = $data['collection'] ?? 'default';
                    $uploadedCount = 0;

                    foreach ($data['files'] ?? [] as $path) {
                        $fullPath = storage_path('app/public/'.$path);

                        if (! file_exists($fullPath)) {
                            continue;
                        }

                        $originalName = pathinfo($path, PATHINFO_BASENAME);
                        $name = pathinfo($originalName, PATHINFO_FILENAME);

                        $media = new Media;
                        $media->save();

                        $media->addMedia($fullPath)
                            ->usingName($name)
                            ->usingFileName($originalName)
                            ->toMediaCollection($collection);

                        $uploadedCount++;
                    }

                    Notification::make()
                        ->title("Nahráno {$uploadedCount} ".($uploadedCount === 1 ? 'soubor' : ($uploadedCount < 5 ? 'soubory' : 'souborů')))
                        ->success()
                        ->send();
                }),

            Action::make('toggleView')
                ->label($this->viewMode === 'grid' ? 'Zobrazit jako seznam' : 'Zobrazit jako mřížku')
                ->icon($this->viewMode === 'grid' ? 'far-list' : 'far-grid-2')
                ->color('gray')
                ->url(fn (): string => static::$resource::getUrl('index', [
                    'view' => $this->viewMode === 'grid' ? 'list' : 'grid',
                ])),
        ];
    }

    public function getTitle(): string
    {
        return 'Média';
    }

    private function getFileIconUrl(Media $record): string
    {
        return '';
    }
}
