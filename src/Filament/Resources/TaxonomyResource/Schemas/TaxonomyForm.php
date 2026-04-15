<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TaxonomyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->schema([
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 3])
                        ->schema([
                            Section::make(__('mipress::admin.resources.taxonomy.form.sections.basic_information'))
                                ->icon('fal-tag')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('title')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.title'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('mipress::admin.resources.taxonomy.form.placeholders.title'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                if (($get('handle') ?? '') !== Str::slug($old)) {
                                                    return;
                                                }

                                                $set('handle', Str::slug($state));
                                            }),
                                        TextInput::make('handle')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.handle'))
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->placeholder(__('mipress::admin.resources.taxonomy.form.placeholders.handle'))
                                            ->helperText(__('mipress::admin.resources.taxonomy.form.help.handle'))
                                            ->disabled(fn ($record): bool => $record !== null),
                                    ]),
                                    Textarea::make('description')
                                        ->label(__('mipress::admin.resources.taxonomy.form.fields.description'))
                                        ->nullable()
                                        ->rows(3)
                                        ->helperText(__('mipress::admin.resources.taxonomy.form.help.description')),
                                ]),
                            Section::make(__('mipress::admin.resources.taxonomy.form.sections.entries_table'))
                                ->icon('fal-table-columns')
                                ->collapsible()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Toggle::make('show_in_entries_table')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.show_in_entries_table'))
                                            ->default(true),
                                        Toggle::make('show_in_entries_filter')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.show_in_entries_filter'))
                                            ->default(true),
                                    ]),
                                    Grid::make(2)->schema([
                                        Toggle::make('searchable_in_entries_table')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.searchable_in_entries_table'))
                                            ->default(false),
                                        Toggle::make('sortable_in_entries_table')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.sortable_in_entries_table'))
                                            ->default(false),
                                    ]),
                                    Grid::make(2)->schema([
                                        Select::make('entries_table_display_mode')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.entries_table_display_mode'))
                                            ->options([
                                                'badges' => __('mipress::admin.resources.taxonomy.form.options.display_mode.badges'),
                                                'text' => __('mipress::admin.resources.taxonomy.form.options.display_mode.text'),
                                            ])
                                            ->default('badges')
                                            ->required()
                                            ->native(false)
                                            ->live(),
                                        Select::make('entries_table_badge_palette')
                                            ->label(__('mipress::admin.resources.taxonomy.form.fields.entries_table_badge_palette'))
                                            ->options([
                                                'neutral' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.neutral'),
                                                'primary' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.primary'),
                                                'success' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.success'),
                                                'warning' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.warning'),
                                                'danger' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.danger'),
                                                'info' => __('mipress::admin.resources.taxonomy.form.options.badge_palette.info'),
                                            ])
                                            ->default('neutral')
                                            ->required()
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => ($get('entries_table_display_mode') ?? 'badges') === 'badges'),
                                    ]),
                                ]),
                        ]),
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 1])
                        ->schema([
                            Section::make(__('mipress::admin.resources.taxonomy.form.sections.settings'))
                                ->icon('fal-gear')
                                ->schema([
                                    Toggle::make('is_hierarchical')
                                        ->label(__('mipress::admin.resources.taxonomy.form.fields.is_hierarchical'))
                                        ->helperText(__('mipress::admin.resources.taxonomy.form.help.hierarchical')),
                                    Select::make('blueprint_id')
                                        ->label(__('mipress::admin.resources.taxonomy.form.fields.blueprint'))
                                        ->relationship('blueprint', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText(__('mipress::admin.resources.taxonomy.form.help.blueprint')),
                                ]),
                            Section::make(__('mipress::admin.resources.taxonomy.form.sections.collection_assignment'))
                                ->icon('fal-folder-tree')
                                ->schema([
                                    Select::make('collection_id')
                                        ->label(__('mipress::admin.resources.taxonomy.form.fields.collection'))
                                        ->relationship('collection', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText(__('mipress::admin.resources.taxonomy.form.help.collection')),
                                ]),
                        ]),
                ]),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }
}
