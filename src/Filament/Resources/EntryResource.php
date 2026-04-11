<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources;

use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Resource;
use Filament\Resources\Pages\Page as FilamentPage;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource\Pages\CreateEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\EditEntry;
use MiPress\Core\Filament\Resources\EntryResource\Pages\EntryHistory;
use MiPress\Core\Filament\Resources\EntryResource\Pages\ListEntries;
use MiPress\Core\Filament\Resources\EntryResource\Schemas\EntryForm;
use MiPress\Core\Filament\Resources\EntryResource\Tables\EntriesTable;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'fal-file-lines';

    protected static string|\UnitEnum|null $navigationGroup = 'Obsah';

    protected static ?string $modelLabel = 'Položka';

    protected static ?string $pluralModelLabel = 'Položky';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getNavigationItems(): array
    {
        $collections = Collection::ordered()
            ->where('handle', '!=', 'pages')
            ->get();

        $inReviewCounts = [];

        if (static::shouldShowInReviewBadges()) {
            $inReviewCounts = Entry::query()
                ->where('status', EntryStatus::InReview)
                ->selectRaw('collection_id, COUNT(*) as aggregate')
                ->groupBy('collection_id')
                ->pluck('aggregate', 'collection_id')
                ->map(fn (mixed $count): int => (int) $count)
                ->all();
        }

        return $collections
            ->map(fn (Collection $collection) => NavigationItem::make($collection->name)
                ->icon($collection->icon ?? 'fal-file-lines')
                ->group('Obsah')
                ->sort($collection->sort_order)
                ->url(static::getUrl('index', ['collection' => $collection->handle]))
                ->isActiveWhen(fn () => static::getCurrentCollection()?->handle === $collection->handle)
                ->badge(
                    ($inReviewCounts[$collection->id] ?? 0) > 0 ? (string) ($inReviewCounts[$collection->id] ?? 0) : null,
                    'warning',
                )
            )
            ->toArray();
    }

    private static function shouldShowInReviewBadges(): bool
    {
        return auth()->user()?->hasPermissionTo('entry.publish') === true;
    }

    public static function resolveCollectionByHandle(?string $handle): ?Collection
    {
        if (! is_string($handle) || blank($handle)) {
            return null;
        }

        $request = request();
        $cacheKey = 'mipress.current_collection.'.$handle;

        if ($request->attributes->has($cacheKey)) {
            /** @var Collection|null $cachedCollection */
            $cachedCollection = $request->attributes->get($cacheKey);

            return $cachedCollection;
        }

        $collection = Collection::query()
            ->where('handle', $handle)
            ->with(['blueprint', 'taxonomies'])
            ->first();

        $request->attributes->set($cacheKey, $collection);

        return $collection;
    }

    public static function getCurrentCollection(): ?Collection
    {
        $routeHandle = request()->route('collection');
        $queryHandle = request()->query('collection');
        $handle = is_string($routeHandle) && filled($routeHandle)
            ? $routeHandle
            : (is_string($queryHandle) ? $queryHandle : null);

        if (! $handle) {
            return null;
        }

        return static::resolveCollectionByHandle($handle);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with([
                'featuredImage',
                'terms',
            ]);

        $collection = static::getCurrentCollection();

        if ($collection) {
            $query->where('collection_id', $collection->id);
        }

        return $query;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'slug', 'collection.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Entry $record */
        return [
            'Sekce' => $record->collection?->name ?? '—',
            'Stav' => $record->status->getLabel(),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['collection']);
    }

    public static function form(Schema $schema): Schema
    {
        return EntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EntriesTable::table($table);
    }

    public static function getRecordSubNavigation(FilamentPage $page): array
    {
        return $page->generateNavigationItems([
            EditEntry::class,
            EntryHistory::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateEntry::route('/{collection?}/create'),
            'index' => ListEntries::route('/{collection?}'),
            'edit' => EditEntry::route('/{record}/edit'),
            'history' => EntryHistory::route('/{record}/history'),
        ];
    }
}
