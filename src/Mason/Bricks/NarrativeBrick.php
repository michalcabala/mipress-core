<?php

declare(strict_types=1);

namespace MiPress\Core\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class NarrativeBrick extends Brick
{
    public static function getId(): string
    {
        return 'narrative';
    }

    public static function getLabel(): string
    {
        return 'Textový blok';
    }

    public static function getIcon(): string
    {
        return 'fal-file-lines';
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('mipress::mason.bricks.narrative.index', [
            'eyebrow' => $config['eyebrow'] ?? null,
            'heading' => $config['heading'] ?? null,
            'content' => $config['content'] ?? null,
            'tone' => $config['tone'] ?? 'default',
            'width' => $config['width'] ?? 'wide',
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label('Štítek')
                    ->maxLength(80),
                TextInput::make('heading')
                    ->label('Nadpis')
                    ->maxLength(140),
                Select::make('tone')
                    ->label('Tón bloku')
                    ->options([
                        'default' => 'Standard',
                        'muted' => 'Zvýrazněný panel',
                        'accent' => 'Akcentní panel',
                    ])
                    ->default('default'),
                Select::make('width')
                    ->label('Šířka textu')
                    ->options([
                        'narrow' => 'Úzká sazba',
                        'wide' => 'Široká sazba',
                    ])
                    ->default('wide'),
                RichEditor::make('content')
                    ->label('Obsah')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
