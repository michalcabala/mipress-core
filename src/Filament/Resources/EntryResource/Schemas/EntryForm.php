<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Schemas;

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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;

class EntryForm
{
    public static function form(Schema $schema): Schema
    {
        $collection = EntryResource::getCurrentCollection();

        if (! $collection) {
            $record = $schema->getRecord();

            if ($record instanceof Entry && $record->collection_id) {
                $collection = $record->load('collection.blueprint')->collection;
            }
        }

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
                        ...self::buildBlueprintSections($blueprint),
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
                $component = self::buildFieldComponent($fieldDef);

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
}
