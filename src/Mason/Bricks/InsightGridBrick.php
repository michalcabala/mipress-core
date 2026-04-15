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
        return __('mipress::admin.mason_bricks.insight_grid.label');
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
                    ->label(__('mipress::admin.mason_bricks.insight_grid.fields.heading'))
                    ->required(),
                Textarea::make('intro')
                    ->label(__('mipress::admin.mason_bricks.insight_grid.fields.intro'))
                    ->rows(3),
                Select::make('columns')
                    ->label(__('mipress::admin.mason_bricks.insight_grid.fields.columns'))
                    ->options([
                        '2' => __('mipress::admin.mason_bricks.insight_grid.options.columns_2'),
                        '3' => __('mipress::admin.mason_bricks.insight_grid.options.columns_3'),
                    ])
                    ->default('3'),
                Repeater::make('items')
                    ->label(__('mipress::admin.mason_bricks.insight_grid.fields.items'))
                    ->defaultItems(3)
                    ->schema([
                        TextInput::make('label')
                            ->label(__('mipress::admin.mason_bricks.insight_grid.fields.label'))
                            ->maxLength(80),
                        TextInput::make('title')
                            ->label(__('mipress::admin.mason_bricks.insight_grid.fields.title'))
                            ->required()
                            ->maxLength(120),
                        Textarea::make('text')
                            ->label(__('mipress::admin.mason_bricks.insight_grid.fields.text'))
                            ->rows(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
