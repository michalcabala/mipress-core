<?php

declare(strict_types=1);

namespace MiPress\Core\Enums;

use Filament\Support\Contracts\HasLabel;

enum EntryStatus: string implements HasLabel
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

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::InReview => 'info',
            self::Published => 'success',
            self::Scheduled => 'warning',
            self::Rejected => 'danger',
        };
    }
}
