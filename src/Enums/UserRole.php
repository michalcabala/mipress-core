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
            self::SuperAdmin => __('mipress::admin.enums.user_role.super_admin'),
            self::Admin => __('mipress::admin.enums.user_role.admin'),
            self::Editor => __('mipress::admin.enums.user_role.editor'),
            self::Contributor => __('mipress::admin.enums.user_role.contributor'),
        };
    }
}
