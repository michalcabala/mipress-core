<?php

declare(strict_types=1);

namespace MiPress\Core\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MediaType: string implements HasColor, HasIcon, HasLabel
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';
    case Archive = 'archive';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Image => 'Obrázek',
            self::Document => 'Dokument',
            self::Video => 'Video',
            self::Audio => 'Audio',
            self::Archive => 'Archiv',
            self::Other => 'Ostatní',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Image => 'success',
            self::Document => 'info',
            self::Video => 'warning',
            self::Audio => 'purple',
            self::Archive => 'gray',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Image => 'far-image',
            self::Document => 'far-file-lines',
            self::Video => 'far-film',
            self::Audio => 'far-music',
            self::Archive => 'far-file-zipper',
            self::Other => 'far-file',
        };
    }

    /** @param string[] $imageMimes */
    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::Image,
            str_starts_with($mimeType, 'video/') => self::Video,
            str_starts_with($mimeType, 'audio/') => self::Audio,
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ]) => self::Document,
            in_array($mimeType, [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
            ]) => self::Archive,
            default => self::Other,
        };
    }
}
