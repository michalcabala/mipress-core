<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use MiPress\Core\Models\Media;

class MediaPicker extends Field
{
    protected string $view = 'mipress::filament.forms.components.media-picker';

    protected bool|Closure $isMultiple = false;

    protected string|Closure $collection = 'default';

    final public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public static function make(string $name): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public function multiple(bool|Closure $multiple = true): static
    {
        $this->isMultiple = $multiple;

        return $this;
    }

    public function collection(string|Closure $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function getCollection(): string
    {
        return $this->evaluate($this->collection);
    }

    /** @return array<int, array{id: int, name: string, thumbnail: string, type: string}> */
    public function getSelectedMedia(): array
    {
        $state = $this->getState();

        if (empty($state)) {
            return [];
        }

        $ids = is_array($state) ? $state : [$state];

        return Media::whereIn('id', $ids)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name,
                'thumbnail' => $media->getThumbnailUrl() ?: $media->getUrl(),
                'type' => $media->getMediaType()->getLabel(),
                'isImage' => $media->isImage(),
                'isSvg' => $media->isSvg(),
            ])
            ->toArray();
    }

    /** @return array<int, array{id: int, name: string, thumbnail: string}> */
    public function getMediaOptions(string $search = '', int $limit = 24): array
    {
        return Media::query()
            ->when($search, fn ($q) => $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('alt', 'LIKE', "%{$search}%"))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->name,
                'thumbnail' => $media->getThumbnailUrl() ?: $media->getUrl(),
                'type' => $media->getMediaType()->getLabel(),
                'isImage' => $media->isImage(),
                'isSvg' => $media->isSvg(),
            ])
            ->toArray();
    }
}
