<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Media;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->hasPermissionTo('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->hasPermissionTo('media.update');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->hasPermissionTo('media.delete');
    }

    public function restore(User $user, Media $media): bool
    {
        return $user->hasPermissionTo('media.delete');
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }
}
