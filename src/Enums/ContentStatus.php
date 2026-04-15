<?php

declare(strict_types=1);

namespace MiPress\Core\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ContentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('mipress::admin.enums.content_status.draft'),
            self::InReview => __('mipress::admin.enums.content_status.in_review'),
            self::Published => __('mipress::admin.enums.content_status.published'),
            self::Scheduled => __('mipress::admin.enums.content_status.scheduled'),
            self::Rejected => __('mipress::admin.enums.content_status.rejected'),
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
