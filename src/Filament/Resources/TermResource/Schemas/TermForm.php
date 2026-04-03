<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;

class TermForm
{
    public static function form(Schema $schema): Schema
    {
        $taxonomy = self::getTaxonomy($schema);

        return $schema->components([
            Section::make('Základní informace')->schema([
                TextInput::make('title')
                    ->label('Název')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(table: 'terms', column: 'slug', ignoreRecord: true)
                    ->maxLength(255),
                Select::make('parent_id')
                    ->label('Nadřazený term')
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
        $taxonomyId = request()->query('taxonomy_id')
            ?? $schema->getLivewire()?->record?->taxonomy_id;

        if (! $taxonomyId) {
            return null;
        }

        return Taxonomy::find($taxonomyId);
    }
}
