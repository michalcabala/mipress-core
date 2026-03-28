<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\CreateCollection;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\EditCollection;
use MiPress\Core\Filament\Resources\CollectionResource\Pages\ListCollections;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-layer-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $modelLabel = 'Sekce';

    protected static ?string $pluralModelLabel = 'Sekce';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základní informace')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('handle')
                        ->label('Handle')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Unikátní identifikátor (např. pages, articles)'),
                ]),
                Grid::make(2)->schema([
                    Select::make('blueprint_id')
                        ->label('Šablona')
                        ->relationship('blueprint', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    TextInput::make('icon')
                        ->label('Ikona')
                        ->nullable()
                        ->maxLength(100)
                        ->placeholder('fas-file-lines')
                        ->helperText('Název Blade iconу (např. fas-file-lines)'),
                ]),
            ]),

            Section::make('Nastavení obsahu')->schema([
                Grid::make(2)->schema([
                    Toggle::make('dated')
                        ->label('Datovaný obsah')
                        ->helperText('Záznamy mají datum publikování'),
                    Toggle::make('slugs')
                        ->label('Používat slug')
                        ->default(true),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('route')
                        ->label('URL vzor')
                        ->nullable()
                        ->maxLength(255)
                        ->placeholder('/{slug}'),
                    Select::make('sort_direction')
                        ->label('Směr řazení')
                        ->options([
                            'asc' => 'Vzestupně',
                            'desc' => 'Sestupně',
                        ])
                        ->default('asc'),
                ]),
                TextInput::make('sort_order')
                    ->label('Pořadí v navigaci')
                    ->numeric()
                    ->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->formatStateUsing(fn (?string $state) => $state)
                    ->html()
                    ->formatStateUsing(fn (?string $state) => $state
                        ? '<x-dynamic-component :component="'.e($state).'" class="h-5 w-5" />'
                        : '')
                    ->width('40px'),
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('blueprint.name')
                    ->label('Šablona')
                    ->sortable(),
                IconColumn::make('dated')
                    ->label('Datovaný')
                    ->boolean(),
                TextColumn::make('entries_count')
                    ->label('Záznamů')
                    ->counts('entries')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Pořadí')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
            'create' => CreateCollection::route('/create'),
            'edit' => EditCollection::route('/{record}/edit'),
        ];
    }
}
