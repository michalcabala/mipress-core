<?php

declare(strict_types=1);

namespace MiPress\Core\Policies\Concerns;

use App\Models\User;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

trait HandlesEntryLikePolicyChecks
{
    protected function canViewAnyContent(User $user): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    protected function canViewContent(User $user): bool
    {
        return $user->hasPermissionTo('entry.view');
    }

    protected function canCreateContent(User $user): bool
    {
        return $user->hasPermissionTo('entry.create');
    }

    protected function canUpdateContent(User $user, Entry|Page $record): bool
    {
        if ($user->hasRole(UserRole::Contributor->value)) {
            if ($record->author_id !== $user->id) {
                return false;
            }

            return in_array($record->status, [EntryStatus::Draft, EntryStatus::InReview, EntryStatus::Rejected, EntryStatus::Published], true);
        }

        return $user->hasPermissionTo('entry.update');
    }

    protected function canDeleteContent(User $user, Entry|Page $record): bool
    {
        if ($record->status === EntryStatus::Published && ! $user->hasPermissionTo('entry.publish')) {
            return false;
        }

        return $user->hasPermissionTo('entry.delete');
    }

    protected function canRestoreContent(User $user, Entry|Page $record): bool
    {
        return $this->canDeleteContent($user, $record);
    }

    protected function canForceDeleteContent(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    protected function canPublishContent(User $user): bool
    {
        return $user->hasPermissionTo('entry.publish');
    }
}
