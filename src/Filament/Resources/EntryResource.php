<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource\Pages\CreateEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\EditEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\ListEntries;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fas-file-lines';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?string $modelLabel = 'Položka';

    protected static ?string $pluralModelLabel = 'Položky';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationItems(): array
    {
        return Collection::ordered()
            ->get()
            ->map(fn (Collection $collection) => NavigationItem::make($collection->name)
                ->icon($collection->icon ?? 'heroicon-o-document')
                ->group('Obsah')
                ->sort($collection->sort_order)
                ->url(static::getUrl('index', ['collection' => $collection->handle]))
                ->isActiveWhen(fn () => request()->query('collection') === $collection->handle)
            )
            ->toArray();
    }

    public static function getCurrentCollection(): ?Collection
    {
        $handle = request()->query('collection');

        if (! $handle) {
            return null;
        }

        return Collection::where('handle', $handle)->with('blueprint')->first();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);

        $collection = static::getCurrentCollection();

        if ($collection) {
            $query->where('collection_id', $collection->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $collection = static::getCurrentCollection();
        $blueprint = $collection?->blueprint;

        $metaFields = [
            TextInput::make('title')
                ->label('Titulek')
                ->required()
                ->maxLength(255),
        ];

        if ($collection?->slugs) {
            $metaFields[] = TextInput::make('slug')
                ->label('Slug')
                ->nullable()
                ->maxLength(255)
                ->helperText('Nechat prázdné pro automatické generování');
        }

        $publishingFields = [
            Select::make('status')
                ->label('Stav')
                ->options(EntryStatus::class)
                ->default(EntryStatus::Draft->value)
                ->required(),
            Select::make('author_id')
                ->label('Autor')
                ->relationship('author', 'name')
                ->searchable()
                ->preload()
                ->default(fn () => auth()->id())
                ->required(),
        ];

        if ($collection?->dated) {
            $publishingFields[] = DateTimePicker::make('published_at')
                ->label('Datum publikování')
                ->nullable();
        }

        if ($collection) {
            $metaFields[] = Hidden::make('collection_id')
                ->default($collection->id);
        }

        $sections = [
            Grid::make([
                'default' => 1,
                'lg' => 3,
            ])->schema([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make('Obsah')->schema($metaFields),
                        ...static::buildBlueprintSections($blueprint),
                    ]),
                Section::make('Publikování')
                    ->columnSpan(1)
                    ->schema($publishingFields),
            ]),
        ];

        return $schema->components($sections);
    }

    protected static function buildBlueprintSections(?object $blueprint): array
    {
        if (! $blueprint || empty($blueprint->fields)) {
            return [];
        }

        $sections = [];

        foreach ($blueprint->fields as $sectionDef) {
            $fields = [];

            foreach ($sectionDef['fields'] ?? [] as $fieldDef) {
                $component = static::buildFieldComponent($fieldDef);

                if ($component) {
                    $fields[] = $component;
                }
            }

            if (! empty($fields)) {
                $sections[] = Section::make($sectionDef['section'] ?? 'Pole')
                    ->statePath('data')
                    ->schema($fields);
            }
        }

        return $sections;
    }

    protected static function buildFieldComponent(array $fieldDef): mixed
    {
        $handle = $fieldDef['handle'] ?? null;
        $label = $fieldDef['label'] ?? $handle;
        $required = (bool) ($fieldDef['required'] ?? false);

        if (! $handle) {
            return null;
        }

        $component = match ($fieldDef['type'] ?? 'text') {
            'textarea' => Textarea::make($handle)->label($label)->rows(4),
            'number' => TextInput::make($handle)->label($label)->numeric(),
            'select' => Select::make($handle)->label($label)->options($fieldDef['options'] ?? []),
            'checkbox' => Checkbox::make($handle)->label($label),
            'toggle' => Toggle::make($handle)->label($label),
            'radio' => Radio::make($handle)->label($label)->options($fieldDef['options'] ?? []),
            'datetime' => DateTimePicker::make($handle)->label($label),
            'date' => DatePicker::make($handle)->label($label),
            'media' => TextInput::make($handle)->label($label)->helperText('Curator bude přidán'),
            'color' => ColorPicker::make($handle)->label($label),
            'tags' => TagsInput::make($handle)->label($label),
            'repeater' => Repeater::make($handle)->label($label)->schema([
                TextInput::make('value')->label('Hodnota'),
            ])->addActionLabel('Přidat záznam'),
            'keyvalue' => KeyValue::make($handle)->label($label),
            'markdown' => MarkdownEditor::make($handle)->label($label),
            'hidden' => Hidden::make($handle),
            'mason' => TextInput::make($handle)->label($label)->helperText('Mason bricks budou definovány'),
            default => TextInput::make($handle)->label($label)->maxLength(255),
        };

        if ($required) {
            $component->required();
        }

        return $component;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titulek')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge()
                    ->color(fn (EntryStatus $state) => $state->getColor())
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Publikováno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Upraveno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                RestoreAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEntries::route('/'),
            'create' => CreateEntry::route('/create'),
            'edit' => EditEntry::route('/{record}/edit'),
        ];
    }
}
