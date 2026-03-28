<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Models\Blueprint;

class BlueprintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('blueprint.view');
    }

    public function view(User $user, Blueprint $blueprint): bool
    {
        return $user->hasPermissionTo('blueprint.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('blueprint.create');
    }

    public function update(User $user, Blueprint $blueprint): bool
    {
        return $user->hasPermissionTo('blueprint.update');
    }

    public function delete(User $user, Blueprint $blueprint): bool
    {
        return $user->hasPermissionTo('blueprint.delete');
    }

    public function restore(User $user, Blueprint $blueprint): bool
    {
        return $user->hasPermissionTo('blueprint.delete');
    }

    public function forceDelete(User $user, Blueprint $blueprint): bool
    {
        return $user->hasPermissionTo('blueprint.delete');
    }
}
