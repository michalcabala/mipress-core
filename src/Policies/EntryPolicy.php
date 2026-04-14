<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Models\Entry;
use MiPress\Core\Policies\Concerns\HandlesEntryLikePolicyChecks;

class EntryPolicy
{
    use HandlesEntryLikePolicyChecks;

    public function viewAny(User $user): bool
    {
        return $this->canViewAnyContent($user);
    }

    public function view(User $user, Entry $entry): bool
    {
        return $this->canViewContent($user);
    }

    public function create(User $user): bool
    {
        return $this->canCreateContent($user);
    }

    public function update(User $user, Entry $entry): bool
    {
        return $this->canUpdateContent($user, $entry);
    }

    public function delete(User $user, Entry $entry): bool
    {
        return $this->canDeleteContent($user, $entry);
    }

    public function restore(User $user, Entry $entry): bool
    {
        return $this->canRestoreContent($user, $entry);
    }

    public function forceDelete(User $user, Entry $entry): bool
    {
        return $this->canForceDeleteContent($user);
    }

    public function publish(User $user, Entry $entry): bool
    {
        return $this->canPublishContent($user);
    }
}
