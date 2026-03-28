<?php

declare(strict_types=1);

namespace MiPress\Core\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use MiPress\Core\Models\Media;

class MiPressImage extends Component
{
    public readonly ?Media $media;

    public readonly string $alt;

    public readonly string $src;

    public readonly string $srcset;

    public function __construct(
        Media|int|null $media = null,
        public readonly string $size = 'medium',
        string $alt = '',
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly string $class = '',
        public readonly bool $lazy = true,
    ) {
        $this->media = $media instanceof Media
            ? $media
            : ($media !== null ? Media::find($media) : null);

        $this->alt = $alt ?: ($this->media?->alt ?? '');
        $this->src = $this->resolveSrc();
        $this->srcset = $this->resolveSrcset();
    }

    public function render(): View
    {
        return view('mipress::components.mipress-image');
    }

    public function shouldRender(): bool
    {
        return $this->media !== null && filled($this->src);
    }

    private function resolveSrc(): string
    {
        if (! $this->media) {
            return '';
        }

        if ($this->media->isSvg()) {
            return $this->media->getUrl();
        }

        $conversion = $this->size;

        if ($this->media->hasGeneratedConversion($conversion)) {
            return $this->media->getUrl($conversion);
        }

        // Fallback chain
        foreach (['medium', 'small', 'large', 'thumbnail'] as $fallback) {
            if ($this->media->hasGeneratedConversion($fallback)) {
                return $this->media->getUrl($fallback);
            }
        }

        return $this->media->getUrl();
    }

    private function resolveSrcset(): string
    {
        if (! $this->media || $this->media->isSvg() || ! $this->media->isImage()) {
            return '';
        }

        $entries = [];

        $conversions = [
            'small' => '400w',
            'medium' => '800w',
            'large' => '1600w',
        ];

        foreach ($conversions as $conversion => $descriptor) {
            if ($this->media->hasGeneratedConversion($conversion)) {
                $entries[] = $this->media->getUrl($conversion).' '.$descriptor;
            }
        }

        return implode(', ', $entries);
    }
}
