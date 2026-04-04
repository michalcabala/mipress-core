<?php

declare(strict_types=1);

namespace MiPress\Core\Policies;

use App\Models\User;
use MiPress\Core\Enums\UserRole;
use MuhammadNawlo\FilamentSitemapGenerator\Models\SitemapSetting;

class SitemapSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_sitemap.manage');
    }

    public function view(User $user, SitemapSetting $sitemapSetting): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_sitemap.manage');
    }

    public function create(User $user): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_sitemap.manage');
    }

    public function update(User $user, SitemapSetting $sitemapSetting): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_sitemap.manage');
    }

    public function delete(User $user, SitemapSetting $sitemapSetting): bool
    {
        return $this->canManageSeoTools($user) && $user->hasPermissionTo('seo_sitemap.manage');
    }

    private function canManageSeoTools(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
