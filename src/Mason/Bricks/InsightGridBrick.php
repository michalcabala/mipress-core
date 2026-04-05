<?php

declare(strict_types=1);

namespace MiPress\Core\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class InsightGridBrick extends Brick
{
    public static function getId(): string
    {
        return 'insight-grid';
    }

    public static function getLabel(): string
    {
        return 'Blok poznatků';
    }

    public static function getIcon(): string
    {
        return 'fal-table-cells-large';
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('mipress::mason.bricks.insight-grid.index', [
            'heading' => $config['heading'] ?? null,
            'intro' => $config['intro'] ?? null,
            'items' => $config['items'] ?? [],
            'columns' => $config['columns'] ?? '3',
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('heading')
                    ->label('Nadpis')
                    ->required(),
                Textarea::make('intro')
                    ->label('Úvod')
                    ->rows(3),
                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([
                        '2' => '2 sloupce',
                        '3' => '3 sloupce',
                    ])
                    ->default('3'),
                Repeater::make('items')
                    ->label('Karty')
                    ->defaultItems(3)
                    ->schema([
                        TextInput::make('label')
                            ->label('Štítek')
                            ->maxLength(80),
                        TextInput::make('title')
                            ->label('Titulek')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('text')
                            ->label('Text')
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
