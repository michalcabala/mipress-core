<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use Awcodes\Botly\Models\Botly;
use MiPress\Core\Enums\UserRole;

class BotlyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_robots.manage');
    }

    public function view(User $user, Botly $botly): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_robots.manage');
    }

    public function create(User $user): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_robots.manage');
    }

    public function update(User $user, Botly $botly): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_robots.manage');
    }

    public function delete(User $user, Botly $botly): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_robots.manage');
    }

    private function canManageSeoTools(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
