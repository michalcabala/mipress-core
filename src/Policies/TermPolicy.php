<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Term;

class TermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('taxonomy.view')
            || $user->hasPermissionTo('entry.view');
    }

    public function view(User $user, Term $term): bool
    {
        return $user->hasPermissionTo('taxonomy.view')
            || $user->hasPermissionTo('entry.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('taxonomy.create');
    }

    public function update(User $user, Term $term): bool
    {
        return $user->hasPermissionTo('taxonomy.update');
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->hasPermissionTo('taxonomy.delete');
    }

    public function restore(User $user, Term $term): bool
    {
        return $user->hasPermissionTo('taxonomy.delete');
    }

    public function forceDelete(User $user, Term $term): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
