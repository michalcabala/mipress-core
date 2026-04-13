<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\CuratorMedia;

class CuratorMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function view(User $user, CuratorMedia $media): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.upload');
    }

    public function update(User $user, CuratorMedia $media): bool
    {
        if ($user->hasRole(UserRole::Contributor->value)) {
            return $media->uploaded_by === $user->id;
        }

        return $user->hasPermissionTo('media.update');
    }

    public function delete(User $user, CuratorMedia $media): bool
    {
        if ($user->hasRole(UserRole::Contributor->value)) {
            return false;
        }

        return $user->hasPermissionTo('media.delete');
    }

    public function restore(User $user, CuratorMedia $media): bool
    {
        return $this->delete($user, $media);
    }

    public function forceDelete(User $user, CuratorMedia $media): bool
    {
        return $this->delete($user, $media);
    }
}
