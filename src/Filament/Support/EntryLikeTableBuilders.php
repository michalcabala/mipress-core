<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Support;

use App\Models\User;
use Closure;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Filament\Tables\Filters\UserSelectFilter;

class EntryLikeTableBuilders
{
    public static function makeSlugColumn(): TextColumn
    {
        return TextColumn::make('slug')
            ->label(__('mipress::admin.entry_like_table.slug'))
            ->searchable()
            ->sortable()
            ->copyable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->default('—');
    }

    public static function makeStatusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('mipress::admin.entry_like_table.status'))
            ->badge()
            ->icon(fn (ContentStatus $state): ?string => $state->getIcon())
            ->color(fn (ContentStatus $state) => $state->getColor())
            ->sortable();
    }

    public static function makeUpdatedAtColumn(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('mipress::admin.entry_like_table.date'))
            ->isoDateTime('LLL')
            ->description(fn (Model $record): ?string => filled($record->created_at) && filled($record->updated_at) && $record->updated_at->gt($record->created_at)
                ? __('mipress::admin.entry_like_table.created_at_description', ['date' => $record->created_at->isoFormat('LLL')])
                : null)
            ->sortable()
            ->toggleable();
    }

    public static function makeAuthorColumn(): UserColumn
    {
        return UserColumn::make('author.name')
            ->label(__('mipress::admin.entry_like_table.author'))
            ->state(fn (Model $record): mixed => $record->author)
            ->sortable()
            ->toggleable()
            ->wrapped();
    }

    public static function makeStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('mipress::admin.entry_like_table.status'))
            ->options(ContentStatus::class);
    }

    public static function makeAuthorFilter(Closure $optionsResolver): UserSelectFilter
    {
        return UserSelectFilter::make('author_id')
            ->label(__('mipress::admin.entry_like_table.author'))
            ->options($optionsResolver)
            ->multiple()
            ->searchable();
    }

    public static function makeCreatedMonthFilter(Closure $optionsResolver): SelectFilter
    {
        return SelectFilter::make('created_month')
            ->label(__('mipress::admin.entry_like_table.month'))
            ->options($optionsResolver)
            ->query(function (Builder $query, array $data): Builder {
                $value = $data['value'] ?? null;

                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}$/', $value)) {
                    return $query;
                }

                [$year, $month] = explode('-', $value);

                return $query
                    ->whereYear('created_at', (int) $year)
                    ->whereMonth('created_at', (int) $month);
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int, Section|null>  $extraSections
     * @return array<int, Section>
     */
    public static function buildBaseFiltersFormSchema(array $filters, array $extraSections = []): array
    {
        $sections = [];

        $publicationFilters = array_values(array_filter([
            $filters['status'] ?? null,
            $filters['trashed'] ?? null,
        ]));

        if ($publicationFilters !== []) {
            $sections[] = Section::make(__('mipress::admin.entry_like_table.sections.publication'))
                ->schema($publicationFilters);
        }

        $metadataFilters = array_values(array_filter([
            $filters['author_id'] ?? null,
            $filters['created_month'] ?? null,
        ]));

        if ($metadataFilters !== []) {
            $sections[] = Section::make(__('mipress::admin.entry_like_table.sections.metadata'))
                ->schema($metadataFilters);
        }

        return [
            ...$sections,
            ...array_values(array_filter($extraSections, fn (?Section $section): bool => $section instanceof Section)),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getAuthorFilterOptions(Builder $query): array
    {
        $authorIds = (clone $query)
            ->whereNotNull('author_id')
            ->distinct()
            ->pluck('author_id')
            ->filter();

        if ($authorIds->isEmpty()) {
            return [];
        }

        $authors = User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->get();

        return UserFieldRenderer::mapUsersToOptionLabels($authors);
    }

    /**
     * @return array<string, string>
     */
    public static function getCreatedMonthOptions(Builder $query): array
    {
        $createdMonthExpression = self::getCreatedMonthExpression($query->getModel()->getConnection()->getDriverName());

        $values = (clone $query)
            ->whereNotNull('created_at')
            ->toBase()
            ->selectRaw("{$createdMonthExpression} as created_month")
            ->distinct()
            ->orderByDesc('created_month')
            ->pluck('created_month')
            ->filter(fn (?string $value): bool => filled($value))
            ->values();

        $options = [];

        foreach ($values as $value) {
            try {
                $date = Carbon::createFromFormat('Y-m', $value);
                $date->locale(app()->getLocale());
                $options[$value] = (string) str($date->translatedFormat('F Y'))->ucfirst();
            } catch (\Throwable) {
                $options[$value] = $value;
            }
        }

        return $options;
    }

    private static function getCreatedMonthExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
    }
}
