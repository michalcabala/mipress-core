<?php

declare(strict_types=1);

namespace MiPress\Core\Traits;

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
        return $this->meta_description ?: null;
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
}
