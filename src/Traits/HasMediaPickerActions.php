<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use MiPress\Core\Models\Media;

trait HasMediaPickerActions
{
    /** @return array<int, array{id: int, name: string, thumbnail: string, type: string, isImage: bool, isSvg: bool}> */
    public function getMediaPickerOptions(string $fieldId, string $search = '', int $limit = 48): array
    {
        return Media::query()
            ->when(
                filled($search),
                fn ($q) => $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('alt', 'LIKE', "%{$search}%")
            )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name,
                'thumbnail' => $media->getThumbnailUrl(),
                'url' => $media->getUrl(),
                'type' => $media->getMediaType()->getLabel(),
                'isImage' => $media->isImage(),
                'isSvg' => $media->isSvg(),
            ])
            ->toArray();
    }
}
