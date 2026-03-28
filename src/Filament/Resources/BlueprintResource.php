<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\CreateBlueprint;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\EditBlueprint;
use MiPress\Core\Filament\Resources\BlueprintResource\Pages\ListBlueprints;
use MiPress\Core\Models\Blueprint;

class BlueprintResource extends Resource
{
    protected static ?string $model = Blueprint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-pen-ruler';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?string $modelLabel = 'Šablona';

    protected static ?string $pluralModelLabel = 'Šablony';

    protected static ?int $navigationSort = 20;

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
                        ->helperText('Unikátní identifikátor (např. page, article)'),
                ]),
            ]),

            Section::make('Sekce a pole')->schema([
                Repeater::make('fields')
                    ->label('Sekce')
                    ->schema([
                        TextInput::make('section')
                            ->label('Název sekce')
                            ->required()
                            ->maxLength(255),
                        Repeater::make('fields')
                            ->label('Pole')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('handle')
                                        ->label('Handle')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('label')
                                        ->label('Popisek')
                                        ->required()
                                        ->maxLength(255),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('type')
                                        ->label('Typ pole')
                                        ->required()
                                        ->options([
                                            'text' => 'Text',
                                            'textarea' => 'Textarea',
                                            'richtext' => 'Rich Text',
                                            'mason' => 'Mason',
                                            'number' => 'Číslo',
                                            'select' => 'Výběr',
                                            'checkbox' => 'Checkbox',
                                            'toggle' => 'Přepínač',
                                            'radio' => 'Radio',
                                            'datetime' => 'Datum a čas',
                                            'date' => 'Datum',
                                            'media' => 'Média',
                                            'color' => 'Barva',
                                            'tags' => 'Štítky',
                                            'repeater' => 'Opakovač',
                                            'keyvalue' => 'Klíč–hodnota',
                                            'markdown' => 'Markdown',
                                            'hidden' => 'Skryté',
                                        ]),
                                    Select::make('required')
                                        ->label('Povinné')
                                        ->options([
                                            '0' => 'Ne',
                                            '1' => 'Ano',
                                        ])
                                        ->default('0'),
                                ]),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel('Přidat pole'),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel('Přidat sekci'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('handle')
                    ->label('Handle')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('collections_count')
                    ->label('Sekcí')
                    ->counts('collections')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
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
            'index' => ListBlueprints::route('/'),
            'create' => CreateBlueprint::route('/create'),
            'edit' => EditBlueprint::route('/{record}/edit'),
        ];
    }
}
