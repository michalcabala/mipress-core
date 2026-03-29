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

    public function hasMfaEnabled(): bool
    {
        return (bool) ($this->has_email_authentication ?? false);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Editor->value,
            UserRole::Contributor->value,
        ]);
    }
}
