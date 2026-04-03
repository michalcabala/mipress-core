<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Page;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('entry.create');
    }

    public function update(User $user, Page $page): bool
    {
        if ($user->hasRole(UserRole::Contributor->value)) {
            if ($page->author_id !== $user->id) {
                return false;
            }

            return in_array($page->status, [EntryStatus::Draft, EntryStatus::Rejected], true);
        }

        return $user->hasPermissionTo('entry.update');
    }

    public function delete(User $user, Page $page): bool
    {
        if ($page->status === EntryStatus::Published && ! $user->hasPermissionTo('entry.publish')) {
            return false;
        }

        return $user->hasPermissionTo('entry.delete');
    }

    public function restore(User $user, Page $page): bool
    {
        if ($page->status === EntryStatus::Published && ! $user->hasPermissionTo('entry.publish')) {
            return false;
        }

        return $user->hasPermissionTo('entry.delete');
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->hasPermissionTo('entry.publish');
    }
}
