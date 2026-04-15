<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\TermResource;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;

class TermForm
{
    public static function form(Schema $schema): Schema
    {
        $taxonomy = self::getTaxonomy($schema);

        return $schema->components([
            Section::make(__('mipress::admin.resources.term.form.sections.basic_information'))->schema([
                TextInput::make('title')
                    ->label(__('mipress::admin.resources.term.form.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),
                TextInput::make('slug')
                    ->label(__('mipress::admin.resources.term.form.fields.slug'))
                    ->unique(table: 'terms', column: 'slug', ignoreRecord: true)
                    ->maxLength(255)
                    ->helperText(__('mipress::admin.resources.term.form.help.slug')),
                Select::make('parent_id')
                    ->label(__('mipress::admin.resources.term.form.fields.parent'))
                    ->options(fn ($record): array => Term::query()
                        ->when($taxonomy, fn ($q) => $q->where('taxonomy_id', $taxonomy?->getKey()))
                        ->whereNull('parent_id')
                        ->when($record, fn ($q) => $q->where('id', '!=', $record?->getKey()))
                        ->pluck('title', 'id')
                        ->toArray()
                    )
                    ->nullable()
                    ->searchable()
                    ->visible((bool) $taxonomy?->is_hierarchical),
            ]),
        ]);
    }

    private static function getTaxonomy(Schema $schema): ?Taxonomy
    {
        $livewire = method_exists($schema, 'getLivewire') ? $schema->getLivewire() : null;
        $taxonomyIdentifier = request()->route('taxonomy')
            ?? request()->query('taxonomy')
            ?? request()->query('taxonomy_id')
            ?? ($livewire && property_exists($livewire, 'taxonomyHandle') ? $livewire->taxonomyHandle : null)
            ?? $livewire?->record?->taxonomy_id;

        if (! filled($taxonomyIdentifier)) {
            return null;
        }

        return TermResource::resolveTaxonomy(is_numeric($taxonomyIdentifier)
            ? (int) $taxonomyIdentifier
            : (string) $taxonomyIdentifier);
    }
}
