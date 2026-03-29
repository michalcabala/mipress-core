<?php

declare(strict_types=1);

namespace MiPress\Core\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class PullQuoteBrick extends Brick
{
    public static function getId(): string
    {
        return 'pull-quote';
    }

    public static function getLabel(): string
    {
        return 'Citace';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('mipress::mason.bricks.pull-quote.index', [
            'quote' => $config['quote'] ?? null,
            'author' => $config['author'] ?? null,
            'role' => $config['role'] ?? null,
            'alignment' => $config['alignment'] ?? 'center',
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                Textarea::make('quote')
                    ->label('Citace')
                    ->required()
                    ->rows(4),
                TextInput::make('author')
                    ->label('Autor'),
                TextInput::make('role')
                    ->label('Role / kontext'),
                Select::make('alignment')
                    ->label('Zarovnání')
                    ->options([
                        'start' => 'Vlevo',
                        'center' => 'Na střed',
                    ])
                    ->default('center'),
            ]);
    }
}
