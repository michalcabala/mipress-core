<?php

declare(strict_types=1);

namespace MiPress\Core\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EntryStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Koncept',
            self::InReview => 'Ke schválení',
            self::Published => 'Publikováno',
            self::Scheduled => 'Naplánováno',
            self::Rejected => 'Zamítnuto',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'warning',
            self::Published => 'success',
            self::Scheduled => 'info',
            self::Rejected => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Draft => 'far-file-lines',
            self::InReview => 'far-paper-plane',
            self::Published => 'far-circle-check',
            self::Scheduled => 'far-clock',
            self::Rejected => 'far-circle-xmark',
        };
    }
}
