<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Models\Collection;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('collection.view');
    }

    public function view(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('collection.create');
    }

    public function update(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.update');
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.delete');
    }

    public function restore(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.delete');
    }

    public function forceDelete(User $user, Collection $collection): bool
    {
        return $user->hasPermissionTo('collection.delete');
    }
}
