<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Tables;

use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Models\Media;

class PickerMediaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(self::query())
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('file_name')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Náhled')
                    ->state(fn (Media $record): ?string => $record->isImage() ? mipress_media_url($record, 'thumbnail') : null)
                    ->square()
                    ->size(56),
                TextColumn::make('file_name')
                    ->label('Soubor')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('custom_properties.alt')
                    ->label('Alt')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('mime_type')
                    ->label('MIME')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('mime_group')
                    ->label('Typ')
                    ->options([
                        'image' => 'Obrázky',
                        'video' => 'Video',
                        'document' => 'Dokumenty',
                        'other' => 'Ostatní',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => match ($data['value']) {
                            'image' => $query->where('mime_type', 'like', 'image/%'),
                            'video' => $query->where('mime_type', 'like', 'video/%'),
                            'document' => $query->where('mime_type', 'not like', 'image/%')->where('mime_type', 'not like', 'video/%'),
                            'other' => $query->whereNull('mime_type'),
                            default => $query,
                        },
                    )),
            ])
            ->paginated([12, 24, 48]);
    }

    private static function query(): Builder
    {
        $query = Media::query()
            ->libraryItems()
            ->with('uploader');

        $user = auth()->user();

        if ($user?->isContributor()) {
            $query->where('uploaded_by', $user->getKey());
        }

        return $query;
    }
}
