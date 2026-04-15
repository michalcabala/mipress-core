<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TaxonomyResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Models\Term;

class TermsRelationManager extends RelationManager
{
    protected static string $relationship = 'terms';

    protected static ?string $title = null;

    protected static \BackedEnum|string|null $icon = 'fal-tags';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('mipress::admin.resources.taxonomy.relation_managers.terms.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.fields.title'))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.fields.slug'))
                ->maxLength(255)
                ->helperText(__('mipress::admin.resources.taxonomy.relation_managers.terms.help.slug')),
            Select::make('parent_id')
                ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.fields.parent'))
                ->options(fn (): array => Term::query()
                    ->where('taxonomy_id', $this->getOwnerRecord()->getKey())
                    ->whereNull('parent_id')
                    ->pluck('title', 'id')
                    ->toArray()
                )
                ->nullable()
                ->searchable()
                ->visible(fn (): bool => (bool) $this->getOwnerRecord()->is_hierarchical),
            TextInput::make('sort_order')
                ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.fields.sort_order'))
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.columns.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.columns.slug'))
                    ->searchable(),
                TextColumn::make('parent.title')
                    ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.columns.parent'))
                    ->default(__('mipress::admin.common.empty')),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label(__('mipress::admin.resources.taxonomy.relation_managers.terms.columns.entries_count'))
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
