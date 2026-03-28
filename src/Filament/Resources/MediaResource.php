<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Filament\Resources\MediaResource\Pages\EditMedia;
use MiPress\Core\Filament\Resources\MediaResource\Pages\ListMedia;
use MiPress\Core\Models\Media;

class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|\BackedEnum|null $navigationIcon = 'far-photo-film';

    protected static string|\UnitEnum|null $navigationGroup = 'Média';

    protected static ?string $modelLabel = 'Médium';

    protected static ?string $pluralModelLabel = 'Média';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'file_name', 'alt'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Media $record */
        return [
            'Typ' => $record->getMediaType()->getLabel(),
            'Velikost' => $record->getHumanReadableSize(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'edit' => EditMedia::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderByDesc('created_at');
    }
}
