<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use Awcodes\Curator\Models\Media;
use MiPress\Core\Enums\UserRole;

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
        if ($user->hasRole([UserRole::SuperAdmin->value, UserRole::Admin->value])) {
            return true;
        }

        return $media->uploaded_by === $user->id
            && $user->hasPermissionTo('media.update');
    }

    public function delete(User $user, Media $media): bool
    {
        if ($user->hasRole([UserRole::SuperAdmin->value, UserRole::Admin->value])) {
            return true;
        }

        return $media->uploaded_by === $user->id
            && $user->hasPermissionTo('media.delete');
    }
}
