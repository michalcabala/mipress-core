<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\GlobalSet;

class GlobalSetPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.view');
    }

    public function view(User $user, GlobalSet $globalSet): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.view');
    }

    public function create(User $user): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.create');
    }

    public function update(User $user, GlobalSet $globalSet): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.update');
    }

    public function delete(User $user, GlobalSet $globalSet): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.delete');
    }

    public function restore(User $user, GlobalSet $globalSet): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.delete');
    }

    public function forceDelete(User $user, GlobalSet $globalSet): bool
    {
        return $this->canManageGlobalSets($user) && $user->hasPermissionTo('global_set.delete');
    }

    private function canManageGlobalSets(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
