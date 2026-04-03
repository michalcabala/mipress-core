<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\TermResource\Pages\CreateTerm;
use MiPress\Core\Filament\Resources\TermResource\Pages\EditTerm;
use MiPress\Core\Filament\Resources\TermResource\Pages\ListTerms;
use MiPress\Core\Filament\Resources\TermResource\Schemas\TermForm;
use MiPress\Core\Filament\Resources\TermResource\Tables\TermsTable;
use MiPress\Core\Models\Taxonomy;
use MiPress\Core\Models\Term;

class TermResource extends Resource
{
    protected static ?string $model = Term::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-tag';

    protected static ?string $modelLabel = 'Štítek';

    protected static ?string $pluralModelLabel = 'Štítky';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationItems(): array
    {
        $taxonomies = Taxonomy::with('collections')
            ->orderBy('title')
            ->get();

        $items = [];

        foreach ($taxonomies as $taxonomy) {
            foreach ($taxonomy->collections as $collection) {
                $items[] = NavigationItem::make($taxonomy->title)
                    ->icon('fal-tag')
                    ->group('Obsah')
                    ->parentItem($collection->name)
                    ->sort($collection->sort_order + 1)
                    ->url(static::getUrl('index', ['taxonomy_id' => $taxonomy->getKey()]))
                    ->isActiveWhen(fn () => static::getCurrentTaxonomy()?->getKey() === $taxonomy->getKey());
            }
        }

        return $items;
    }

    public static function getCurrentTaxonomy(): ?Taxonomy
    {
        $request = request();
        $taxonomyId = $request->query('taxonomy_id')
            ?? $request->route('taxonomy_id');

        if (! $taxonomyId) {
            return null;
        }

        return Taxonomy::find($taxonomyId);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $taxonomy = static::getCurrentTaxonomy();

        if ($taxonomy) {
            $query->where('taxonomy_id', $taxonomy->getKey());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return TermForm::form($schema);
    }

    public static function table(Table $table): Table
    {
        return TermsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerms::route('/'),
            'create' => CreateTerm::route('/create'),
            'edit' => EditTerm::route('/{record}/edit'),
        ];
    }
}
