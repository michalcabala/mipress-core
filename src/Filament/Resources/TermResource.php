<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    public static function getCurrentTaxonomyIdentifier(): int|string|null
    {
        $request = request();
        $taxonomy = $request->route('taxonomy')
            ?? $request->query('taxonomy')
            ?? $request->query('taxonomy_id');

        if (! filled($taxonomy)) {
            return null;
        }

        return is_numeric($taxonomy)
            ? (int) $taxonomy
            : (string) $taxonomy;
    }

    public static function getNavigationItems(): array
    {
        /** @var Collection<int, Taxonomy> $taxonomies */
        $taxonomies = Taxonomy::with('collection')
            ->whereNotNull('collection_id')
            ->orderBy('title')
            ->get();

        $items = [];

        foreach ($taxonomies as $taxonomy) {
            /** @var Taxonomy $taxonomy */
            $collection = $taxonomy->collection;

            if (! $collection) {
                continue;
            }

            $items[] = NavigationItem::make($taxonomy->title)
                ->icon('fal-tag')
                ->group('Obsah')
                ->parentItem($collection->name)
                ->sort($collection->sort_order + 1)
                ->url(static::getUrl('index', ['taxonomy' => $taxonomy->handle]))
                ->isActiveWhen(fn (): bool => static::isCurrentTaxonomy($taxonomy));
        }

        return $items;
    }

    public static function getCurrentTaxonomy(): ?Taxonomy
    {
        return static::resolveTaxonomy(static::getCurrentTaxonomyIdentifier());
    }

    public static function resolveTaxonomy(int|string|null $taxonomy): ?Taxonomy
    {
        if (! filled($taxonomy)) {
            return null;
        }

        $request = request();
        $cacheKey = 'mipress.current_taxonomy.'.(string) $taxonomy;

        if ($request->attributes->has($cacheKey)) {
            /** @var Taxonomy|null $cachedTaxonomy */
            $cachedTaxonomy = $request->attributes->get($cacheKey);

            return $cachedTaxonomy;
        }

        $resolvedTaxonomy = is_numeric($taxonomy)
            ? Taxonomy::find((int) $taxonomy)
            : Taxonomy::where('handle', (string) $taxonomy)->first();

        $request->attributes->set($cacheKey, $resolvedTaxonomy);

        return $resolvedTaxonomy;
    }

    public static function isCurrentTaxonomy(Taxonomy $taxonomy): bool
    {
        $currentTaxonomy = static::getCurrentTaxonomyIdentifier();

        if ($currentTaxonomy === null) {
            return false;
        }

        if (is_int($currentTaxonomy)) {
            return $taxonomy->getKey() === $currentTaxonomy;
        }

        return $taxonomy->handle === $currentTaxonomy;
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
            'index' => ListTerms::route('/{taxonomy?}'),
            'create' => CreateTerm::route('/{taxonomy?}/create'),
            'edit' => EditTerm::route('/{record}/edit'),
        ];
    }
}
