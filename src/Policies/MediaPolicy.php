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
        if ($user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Editor->value,
        ])) {
            return true;
        }

        return (int) $media->uploaded_by === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        if ($user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Editor->value,
        ])) {
            return true;
        }

        return (int) $media->uploaded_by === (int) $user->getKey();
    }

    public function delete(User $user, Media $media): bool
    {
        if ($user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ])) {
            return true;
        }

        return (int) $media->uploaded_by === (int) $user->getKey();
    }

    public function regenerateConversions(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }

    public function regenerateAllConversions(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Editor->value,
        ]);
    }
}
