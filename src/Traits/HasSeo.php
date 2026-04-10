<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

use Awcodes\Curator\Models\Media;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

trait HasSeo
{
    public function getSeoTitle(): ?string
    {
        return $this->meta_title ?: ($this->title ?? null);
    }

    public function getSeoDescription(): ?string
    {
        $metaDescription = $this->meta_description ?? null;

        if (is_string($metaDescription) && trim($metaDescription) !== '') {
            return trim($metaDescription);
        }

        if (method_exists($this, 'getExcerpt')) {
            $excerpt = $this->getExcerpt();

            if (is_string($excerpt) && trim($excerpt) !== '') {
                return trim($excerpt);
            }
        }

        return null;
    }

    public function getSeoImageUrl(): ?string
    {
        return $this->resolveSeoMedia()?->url;
    }

    public function getSeoImageAlt(): ?string
    {
        return $this->resolveSeoMedia()?->alt ?: $this->getSeoTitle();
    }

    public function getSeoLocale(): ?string
    {
        $locale = $this->locale ?? null;

        if (! is_string($locale) || trim($locale) === '') {
            return null;
        }

        return trim($locale);
    }

    public static function seoFormSchema(): Section
    {
        return Section::make('SEO')
            ->icon('fal-magnifying-glass')
            ->collapsible()
            ->collapsed()
            ->schema([
                TextInput::make('meta_title')
                    ->label('SEO titulek')
                    ->maxLength(60)
                    ->helperText('Doporučeno 50–60 znaků. Prázdné = použije se titulek stránky.'),
                Textarea::make('meta_description')
                    ->label('SEO popis')
                    ->maxLength(160)
                    ->rows(3)
                    ->helperText('Doporučeno 120–160 znaků.'),
            ]);
    }

    private function resolveSeoMedia(): ?Media
    {
        $relations = [];

        if (method_exists($this, 'ogImage')) {
            $relations[] = 'ogImage';
        }

        if (method_exists($this, 'featuredImage')) {
            $relations[] = 'featuredImage';
        }

        if ($relations !== [] && method_exists($this, 'loadMissing')) {
            $this->loadMissing($relations);
        }

        foreach ($relations as $relation) {
            $media = $this->getRelationValue($relation);

            if ($media instanceof Media) {
                return $media;
            }
        }

        return null;
    }
}
