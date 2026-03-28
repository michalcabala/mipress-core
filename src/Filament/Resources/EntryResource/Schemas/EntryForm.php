<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $hasSlug = (bool) ($collection?->slugs);
        $recordId = $schema->getRecord()?->id;

        $uniqueSlugRule = $collection
            ? Rule::unique('entries', 'slug')
                ->where('collection_id', $collection->id)
                ->ignore($recordId)
            : null;

        $tabs = Tabs::make('entry-form')
            ->persistTabInQueryString('entry-tab')
            ->tabs([
                Tab::make('Obsah')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('Titulek')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                    if (($get('slug') ?? '') !== Str::slug($old)) {
                                        return;
                                    }

                                    $set('slug', Str::slug($state));
                                })
                                ->columnSpan($hasSlug ? 1 : 2),

                            TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->visible($hasSlug)
                                ->maxLength(200)
                                ->rules(array_filter([
                                    'alpha_dash',
                                    $uniqueSlugRule,
                                ])),
                        ]),

                        ...self::buildBlueprintSections($blueprint),
                    ]),

                Tab::make('Nastavení')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->schema([
                        Select::make('status')
                            ->label('Stav')
                            ->options(EntryStatus::class)
                            ->default(EntryStatus::Draft->value)
                            ->required()
                            ->disabled()
                            ->dehydrated(false),

                        Placeholder::make('review_note_notice')
                            ->label('Důvod zamítnutí')
                            ->content(fn (?Entry $record): string => $record?->review_note ?? '')
                            ->visible(fn (?Entry $record): bool => $record?->status === EntryStatus::Rejected),

                        DateTimePicker::make('published_at')
                            ->label('Datum publikování')
                            ->nullable()
                            ->helperText('Nechte prázdné pro okamžité publikování, nebo nastavte budoucí datum pro naplánování.')
                            ->visible(fn (): bool => (bool) auth()->user()?->can('entry.publish')),

                        Select::make('author_id')
                            ->label('Autor')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->id()),

                        TextInput::make('sort_order')
                            ->label('Pořadí')
                            ->numeric()
                            ->default(0),
                    ]),

                Tab::make('SEO')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        Section::make()
                            ->statePath('data')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('SEO titulek')
                                    ->maxLength(60)
                                    ->helperText('Doporučeno: 50–60 znaků. Pokud prázdné, použije se title.'),

                                Textarea::make('meta_description')
                                    ->label('SEO popis')
                                    ->maxLength(160)
                                    ->rows(3)
                                    ->helperText('Doporučeno: 150–160 znaků.'),

                                FileUpload::make('featured_image')
                                    ->label('Hlavní obrázek')
                                    ->image()
                                    ->disk('public')
                                    ->directory('entries')
                                    ->imageEditor()
                                    ->imageEditorAspectRatioOptions([
                                        '16:9' => '16:9',
                                        '4:3' => '4:3',
                                        '1:1' => '1:1',
                                        '1.91:1' => '1.91:1',
                                    ])
                                    ->visibility('public')
                                    ->nullable(),
                            ]),
                    ]),
            ]);

        $components = [$tabs];

        if ($collection) {
            $components[] = Hidden::make('collection_id')
                ->default($collection->id);
        }

        return $schema->components($components);
    }

    /**
     * @return array<int, Section>
     */
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
            'mason' => TextInput::make($handle)->label($label)->helperText('Mason bricks budou definovány'),
            default => TextInput::make($handle)->label($label)->maxLength(255),
        };

        if ($required) {
            $component->required();
        }

        return $component;
    }
}
