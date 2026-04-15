<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CollectionResource\Schemas;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use MiPress\Core\Models\Collection;

class CollectionForm
{
    private const RESERVED_HANDLE = 'pages';

    private const RESERVED_ROOT_ROUTE = '/{slug}';

    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $currentRecord = $record instanceof Collection ? $record : null;

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('mipress::admin.resources.collection.form.sections.basic_information'))
                            ->description(__('mipress::admin.resources.collection.form.descriptions.basic_information'))
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('mipress::admin.resources.collection.form.fields.name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('mipress::admin.resources.collection.form.placeholders.name'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                                $currentHandle = trim((string) ($get('handle') ?? ''));
                                                $oldHandle = Str::slug((string) $old);

                                                if ($currentHandle !== '' && $currentHandle !== $oldHandle) {
                                                    return;
                                                }

                                                $set('handle', Str::slug((string) $state));
                                            }),
                                        TextInput::make('handle')
                                            ->label(__('mipress::admin.resources.collection.form.fields.handle'))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->rules([
                                                static function (string $attribute, mixed $value, Closure $fail) use ($currentRecord): void {
                                                    static::validateReservedHandle($currentRecord, $value, $fail);
                                                },
                                            ])
                                            ->maxLength(255)
                                            ->placeholder(__('mipress::admin.resources.collection.form.placeholders.handle'))
                                            ->helperText(__('mipress::admin.resources.collection.form.help.handle')),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Select::make('blueprint_id')
                                            ->label(__('mipress::admin.resources.collection.form.fields.blueprint'))
                                            ->relationship('blueprint', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->native(false)
                                            ->nullable()
                                            ->helperText(__('mipress::admin.resources.collection.form.help.blueprint')),
                                        TextInput::make('icon')
                                            ->label(__('mipress::admin.resources.collection.form.fields.icon'))
                                            ->nullable()
                                            ->maxLength(100)
                                            ->placeholder(__('mipress::admin.resources.collection.form.placeholders.icon'))
                                            ->helperText(__('mipress::admin.resources.collection.form.help.icon')),
                                    ]),
                            ]),
                        Section::make(__('mipress::admin.resources.collection.form.sections.url_and_sorting'))
                            ->description(__('mipress::admin.resources.collection.form.descriptions.url_and_sorting'))
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('route')
                                            ->label(__('mipress::admin.resources.collection.form.fields.route'))
                                            ->nullable()
                                            ->rules([
                                                static function (string $attribute, mixed $value, Closure $fail) use ($currentRecord): void {
                                                    static::validateReservedRoutePattern($currentRecord, $value, $fail);
                                                },
                                            ])
                                            ->maxLength(255)
                                            ->placeholder(fn (Get $get): string => (bool) $get('slugs')
                                                ? __('mipress::admin.resources.collection.form.placeholders.route_with_slug')
                                                : __('mipress::admin.resources.collection.form.placeholders.route_without_slug'))
                                            ->helperText(fn (Get $get): string => (bool) $get('slugs')
                                                ? __('mipress::admin.resources.collection.form.help.route_with_slug')
                                                : __('mipress::admin.resources.collection.form.help.route_without_slug')),
                                        Select::make('sort_direction')
                                            ->label(__('mipress::admin.resources.collection.form.fields.sort_direction'))
                                            ->options([
                                                'asc' => __('mipress::admin.resources.collection.form.options.sort_direction.asc'),
                                                'desc' => __('mipress::admin.resources.collection.form.options.sort_direction.desc'),
                                            ])
                                            ->default('asc')
                                            ->native(false),
                                    ]),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),
                Group::make()
                    ->schema([
                        Section::make(__('mipress::admin.resources.collection.form.sections.behavior'))
                            ->schema([
                                Toggle::make('dated')
                                    ->label(__('mipress::admin.resources.collection.form.fields.dated'))
                                    ->helperText(__('mipress::admin.resources.collection.form.help.dated'))
                                    ->inline(false),
                                Toggle::make('slugs')
                                    ->label(__('mipress::admin.resources.collection.form.fields.slugs'))
                                    ->default(true)
                                    ->live()
                                    ->helperText(__('mipress::admin.resources.collection.form.help.slugs'))
                                    ->inline(false),
                                Toggle::make('hierarchical')
                                    ->label(__('mipress::admin.resources.collection.form.fields.hierarchical'))
                                    ->default(false)
                                    ->helperText(__('mipress::admin.resources.collection.form.help.hierarchical'))
                                    ->inline(false),
                                TextInput::make('sort_order')
                                    ->label(__('mipress::admin.resources.collection.form.fields.sort_order'))
                                    ->numeric()
                                    ->default(0)
                                    ->helperText(__('mipress::admin.resources.collection.form.help.sort_order')),
                            ]),
                        Section::make(__('mipress::admin.resources.collection.form.sections.taxonomy_assignment'))
                            ->description(__('mipress::admin.resources.collection.form.descriptions.taxonomy_assignment'))
                            ->schema([
                                Select::make('taxonomies')
                                    ->label(__('mipress::admin.resources.collection.form.fields.taxonomies'))
                                    ->relationship('taxonomies', 'title')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->nullable(),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    private static function validateReservedHandle(?Collection $record, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $normalizedValue = mb_strtolower(trim($value));

        if ($normalizedValue !== self::RESERVED_HANDLE) {
            return;
        }

        if (mb_strtolower((string) $record?->handle) === self::RESERVED_HANDLE) {
            return;
        }

        $fail(__('mipress::admin.resources.collection.validation.reserved_handle'));
    }

    private static function validateReservedRoutePattern(?Collection $record, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalizedValue = static::normalizeRoutePattern($value);

        if ($normalizedValue !== self::RESERVED_ROOT_ROUTE) {
            return;
        }

        if (static::normalizeRoutePattern((string) $record?->route) === self::RESERVED_ROOT_ROUTE) {
            return;
        }

        $fail(__('mipress::admin.resources.collection.validation.reserved_route_pattern'));
    }

    private static function normalizeRoutePattern(string $route): string
    {
        $normalized = preg_replace('#/+#', '/', '/'.ltrim(trim($route), '/'));

        return is_string($normalized) ? rtrim($normalized, '/') ?: '/' : trim($route);
    }
}
