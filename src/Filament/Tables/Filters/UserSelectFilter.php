<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Tables\Filters;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Arr;
use MiPress\Core\Filament\Support\UserFields\UserFieldRenderer;

class UserSelectFilter extends SelectFilter
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->getOptionLabelFromRecordUsing(fn (mixed $record): string => UserFieldRenderer::renderOption($record))
            ->modifyFormFieldUsing(fn (Select $select): Select => $select->allowHtml())
            ->indicateUsing(function (UserSelectFilter $filter, array $state): array {
                $selectedValues = $filter->isMultiple()
                    ? Arr::wrap($state['values'] ?? [])
                    : [$state['value'] ?? null];

                $labels = static::resolveIndicatorLabels($filter, $selectedValues);

                if ($labels === []) {
                    return [];
                }

                $label = collect($labels)->join(', ', ' & ');
                $indicator = $filter->getIndicator();

                if (! $indicator instanceof Indicator) {
                    $indicator = Indicator::make("{$indicator}: {$label}");
                }

                return [$indicator];
            });
    }

    /**
     * @param  array<int, mixed>  $selectedValues
     * @return array<int, string>
     */
    private static function resolveIndicatorLabels(UserSelectFilter $filter, array $selectedValues): array
    {
        $selectedValues = array_values(array_filter(
            $selectedValues,
            static fn (mixed $value): bool => filled($value),
        ));

        if ($selectedValues === []) {
            return [];
        }

        $usersById = User::query()
            ->whereKey($selectedValues)
            ->get()
            ->keyBy(fn (User $user): string => (string) $user->getKey());

        return collect($selectedValues)
            ->map(function (mixed $value) use ($filter, $usersById): ?string {
                $user = $usersById->get((string) $value);

                if ($user instanceof User) {
                    return UserFieldRenderer::resolveUserName($user);
                }

                return static::stripHtmlLabel($filter->getOptions()[(string) $value] ?? null);
            })
            ->filter(fn (?string $label): bool => filled($label))
            ->values()
            ->all();
    }

    private static function stripHtmlLabel(mixed $label): ?string
    {
        if (! is_string($label) || trim($label) === '') {
            return null;
        }

        $decodedLabel = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainLabel = trim(strip_tags($decodedLabel));

        return $plainLabel !== '' ? $plainLabel : null;
    }
}
