<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Models\Page;
use MiPress\Core\Policies\Concerns\HandlesEntryLikePolicyChecks;

class PagePolicy
{
    use HandlesEntryLikePolicyChecks;

    public function viewAny(User $user): bool
    {
        return $this->canViewAnyContent($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->canViewContent($user);
    }

    public function create(User $user): bool
    {
        return $this->canCreateContent($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->canUpdateContent($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->canDeleteContent($user, $page);
    }

    public function restore(User $user, Page $page): bool
    {
        return $this->canRestoreContent($user, $page);
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $this->canForceDeleteContent($user);
    }

    public function publish(User $user, Page $page): bool
    {
        return $this->canPublishContent($user);
    }
}
