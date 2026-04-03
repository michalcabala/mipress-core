<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Entry;

class EntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function view(User $user, Entry $entry): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('entry.create');
    }

    public function update(User $user, Entry $entry): bool
    {
        if ($user->hasRole(UserRole::Contributor->value)) {
            if ($entry->author_id !== $user->id) {
                return false;
            }

            return in_array($entry->status, [EntryStatus::Draft, EntryStatus::InReview, EntryStatus::Rejected, EntryStatus::Published], true);
        }

        return $user->hasPermissionTo('entry.update');
    }

    public function delete(User $user, Entry $entry): bool
    {
        if ($entry->status === EntryStatus::Published && ! $user->hasPermissionTo('entry.publish')) {
            return false;
        }

        return $user->hasPermissionTo('entry.delete');
    }

    public function restore(User $user, Entry $entry): bool
    {
        if ($entry->status === EntryStatus::Published && ! $user->hasPermissionTo('entry.publish')) {
            return false;
        }

        return $user->hasPermissionTo('entry.delete');
    }

    public function forceDelete(User $user, Entry $entry): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function publish(User $user, Entry $entry): bool
    {
        return $user->hasPermissionTo('entry.publish');
    }
}
