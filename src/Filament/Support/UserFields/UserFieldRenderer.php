<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Support\UserFields;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class UserFieldRenderer
{
    /**
     * @param  iterable<int, User>  $users
     * @return array<int, string>
     */
    public static function mapUsersToOptionLabels(iterable $users): array
    {
        return collect($users)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->sortBy(fn (User $user): string => mb_strtolower((string) filament()->getUserName($user)))
            ->mapWithKeys(fn (User $user): array => [
                (int) $user->getKey() => static::renderOption($user),
            ])
            ->all();
    }

    public static function renderOption(mixed $user): string
    {
        $resolvedUser = static::resolveUser($user);

        if (! $resolvedUser instanceof User) {
            return '';
        }

        return (string) view('mipress::filament.components.user-fields.user-avatar-option', [
            'user' => $resolvedUser,
        ])->render();
    }

    public static function renderState(mixed $state): string
    {
        $users = static::extractUsers($state);

        if ($users === []) {
            return '—';
        }

        return collect($users)
            ->map(static fn (User $user): string => static::renderOption($user))
            ->implode(' ');
    }

    public static function resolveUserName(mixed $state): ?string
    {
        $user = static::resolveUser($state);

        if (! $user instanceof User) {
            return null;
        }

        return filament()->getUserName($user);
    }

    public static function resolveAvatarUrl(mixed $state, mixed $currentState = null): ?string
    {
        $user = static::resolveUser($state);

        if (! $user instanceof User && is_object($state) && isset($state->id)) {
            $user = collect(static::extractUsers($currentState))
                ->first(fn (User $candidate): bool => (int) $candidate->getKey() === (int) $state->id);
        }

        if (! $user instanceof User) {
            return null;
        }

        return filament()->getUserAvatarUrl($user);
    }

    private static function resolveUser(mixed $value): ?User
    {
        return $value instanceof User ? $value : null;
    }

    /**
     * @return array<int, User>
     */
    private static function extractUsers(mixed $state): array
    {
        if ($state instanceof User) {
            return [$state];
        }

        if ($state instanceof EloquentCollection || $state instanceof Collection) {
            return $state
                ->filter(fn (mixed $item): bool => $item instanceof User)
                ->values()
                ->all();
        }

        if (is_array($state)) {
            return collect($state)
                ->filter(fn (mixed $item): bool => $item instanceof User)
                ->values()
                ->all();
        }

        return [];
    }
}
