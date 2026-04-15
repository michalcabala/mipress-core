<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\BlueprintResource\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use MiPress\Core\FieldTypes\FieldTypeRegistry;

class BlueprintForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('mipress::admin.resources.blueprint.form.sections.basic_information'))->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label(__('mipress::admin.resources.blueprint.form.fields.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('handle')
                        ->label(__('mipress::admin.resources.blueprint.form.fields.handle'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText(__('mipress::admin.resources.blueprint.form.help.handle')),
                ]),
            ]),

            Section::make(__('mipress::admin.resources.blueprint.form.sections.sections_and_fields'))->schema([
                Repeater::make('fields')
                    ->label(__('mipress::admin.resources.blueprint.form.fields.sections'))
                    ->schema([
                        TextInput::make('section')
                            ->label(__('mipress::admin.resources.blueprint.form.fields.section_name'))
                            ->required()
                            ->maxLength(255),
                        Repeater::make('fields')
                            ->label(__('mipress::admin.resources.blueprint.form.fields.fields'))
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('handle')
                                        ->label(__('mipress::admin.resources.blueprint.form.fields.handle'))
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('label')
                                        ->label(__('mipress::admin.resources.blueprint.form.fields.label'))
                                        ->required()
                                        ->maxLength(255),
                                ]),
                                Grid::make(2)->schema([
                                    Select::make('type')
                                        ->label(__('mipress::admin.resources.blueprint.form.fields.field_type'))
                                        ->required()
                                        ->options(fn (): array => app(FieldTypeRegistry::class)->groupedOptions())
                                        ->searchable()
                                        ->live(),
                                    Select::make('required')
                                        ->label(__('mipress::admin.resources.blueprint.form.fields.required'))
                                        ->options([
                                            '0' => __('mipress::admin.common.options.no'),
                                            '1' => __('mipress::admin.common.options.yes'),
                                        ])
                                        ->default('0'),
                                ]),
                                Section::make(__('mipress::admin.resources.blueprint.form.sections.table_display'))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            Checkbox::make('show_in_table')
                                                ->label(__('mipress::admin.resources.blueprint.form.fields.show_in_table'))
                                                ->default(false),
                                            Checkbox::make('searchable')
                                                ->label(__('mipress::admin.resources.blueprint.form.fields.searchable'))
                                                ->default(false),
                                            Checkbox::make('sortable')
                                                ->label(__('mipress::admin.resources.blueprint.form.fields.sortable'))
                                                ->default(false),
                                        ]),
                                    ])
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                                Section::make(__('mipress::admin.resources.blueprint.form.sections.field_settings'))
                                    ->schema(fn (Get $get): array => static::getFieldTypeSettings($get('type')))
                                    ->visible(fn (Get $get): bool => static::hasFieldTypeSettings($get('type')))
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                                Section::make(__('mipress::admin.resources.blueprint.form.sections.conditional_visibility'))
                                    ->schema([
                                        Select::make('config.visibility_mode')
                                            ->label(__('mipress::admin.resources.blueprint.form.fields.visibility_mode'))
                                            ->options([
                                                'all' => __('mipress::admin.resources.blueprint.form.options.visibility_mode.all'),
                                                'any' => __('mipress::admin.resources.blueprint.form.options.visibility_mode.any'),
                                            ])
                                            ->default('all')
                                            ->native(false),
                                        Repeater::make('config.visibility_conditions')
                                            ->label(__('mipress::admin.resources.blueprint.form.fields.conditions'))
                                            ->schema([
                                                Grid::make(2)->schema([
                                                    TextInput::make('field')
                                                        ->label(__('mipress::admin.resources.blueprint.form.fields.condition_field'))
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->helperText(__('mipress::admin.resources.blueprint.form.help.condition_field')),
                                                    Select::make('operator')
                                                        ->label(__('mipress::admin.resources.blueprint.form.fields.operator'))
                                                        ->required()
                                                        ->options([
                                                            'equals' => __('mipress::admin.resources.blueprint.form.options.operators.equals'),
                                                            'not_equals' => __('mipress::admin.resources.blueprint.form.options.operators.not_equals'),
                                                            'contains' => __('mipress::admin.resources.blueprint.form.options.operators.contains'),
                                                            'not_contains' => __('mipress::admin.resources.blueprint.form.options.operators.not_contains'),
                                                            'filled' => __('mipress::admin.resources.blueprint.form.options.operators.filled'),
                                                            'blank' => __('mipress::admin.resources.blueprint.form.options.operators.blank'),
                                                        ])
                                                        ->default('equals')
                                                        ->native(false)
                                                        ->live(),
                                                ]),
                                                TextInput::make('value')
                                                    ->label(__('mipress::admin.resources.blueprint.form.fields.condition_value'))
                                                    ->visible(fn (Get $get): bool => in_array((string) $get('operator'), ['equals', 'not_equals', 'contains', 'not_contains'], true)),
                                            ])
                                            ->reorderable()
                                            ->collapsible()
                                            ->defaultItems(0)
                                            ->addActionLabel(__('mipress::admin.resources.blueprint.form.actions.add_condition')),
                                    ])
                                    ->compact()
                                    ->collapsible()
                                    ->collapsed(),
                            ])
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel(__('mipress::admin.resources.blueprint.form.actions.add_field')),
                    ])
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(0)
                    ->addActionLabel(__('mipress::admin.resources.blueprint.form.actions.add_section')),
            ]),
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function getFieldTypeSettings(?string $type): array
    {
        if (! $type) {
            return [];
        }

        $registry = app(FieldTypeRegistry::class);

        if (! $registry->has($type)) {
            return [];
        }

        return $registry->get($type)->settingsSchema();
    }

    private static function hasFieldTypeSettings(?string $type): bool
    {
        return static::getFieldTypeSettings($type) !== [];
    }
}
