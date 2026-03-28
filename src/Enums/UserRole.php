<?php

declare(strict_types=1);

namespace MiPress\Core\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Editor = 'editor';
    case Contributor = 'contributor';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrátor',
            self::Admin => 'Administrátor',
            self::Editor => 'Editor',
            self::Contributor => 'Přispěvatel',
        };
    }
}
