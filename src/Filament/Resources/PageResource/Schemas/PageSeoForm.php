<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Models\Page;

class PageSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();

        return $schema->components([
            Grid::make(1)
                ->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Page ? self::isReadOnlyForCurrentUser($record) : false)
                ->schema([
                    Section::make('SEO')
                        ->icon('fal-magnifying-glass')
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('meta_title')
                                ->label('SEO titulek')
                                ->maxLength(60)
                                ->helperText('Doporučeno 50-60 znaků. Pokud zůstane prázdný, použije se titulek stránky.'),
                            Textarea::make('meta_description')
                                ->label('SEO popis')
                                ->maxLength(160)
                                ->rows(3)
                                ->helperText('Krátký popis pro výsledky vyhledávání a sdílení.'),
                        ]),
                ]),
        ]);
    }

    private static function isReadOnlyForCurrentUser(Page $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && $record->status === EntryStatus::InReview;
    }
}
