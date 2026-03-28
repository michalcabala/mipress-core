<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Filament\Panel;
use Illuminate\Database\Eloquent\SoftDeletes;
use MiPress\Core\Enums\UserRole;
use Spatie\Permission\Traits\HasRoles as SpatieHasRoles;

trait HasRoles
{
    use SoftDeletes, SpatieHasRoles;

    /**
     * Prevent deleting a SuperAdmin at the model level (safety net).
     * The Filament UserResource also enforces this via canDelete().
     */
    public static function bootHasRoles(): void
    {
        static::deleting(function (self $model): void {
            if ($model->isSuperAdmin()) {
                throw new \RuntimeException('Superadministrátor nemůže být smazán.');
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SuperAdmin->value);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::Admin->value);
    }

    public function isEditor(): bool
    {
        return $this->hasRole(UserRole::Editor->value);
    }

    public function isContributor(): bool
    {
        return $this->hasRole(UserRole::Contributor->value);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // TODO: Enforce 2FA for SuperAdmin once a 2FA package is integrated.
        // if ($this->isSuperAdmin() && ! $this->hasTwoFactorEnabled()) {
        //     return false;
        // }

        return $this->isSuperAdmin() || $this->isAdmin();
    }
}
