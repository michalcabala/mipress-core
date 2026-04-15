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
        return __('mipress::admin.mason_bricks.pull_quote.label');
    }

    public static function getIcon(): string
    {
        return 'fal-comments';
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
                    ->label(__('mipress::admin.mason_bricks.pull_quote.fields.quote'))
                    ->required()
                    ->rows(4),
                TextInput::make('author')
                    ->label(__('mipress::admin.mason_bricks.pull_quote.fields.author')),
                TextInput::make('role')
                    ->label(__('mipress::admin.mason_bricks.pull_quote.fields.role')),
                Select::make('alignment')
                    ->label(__('mipress::admin.mason_bricks.pull_quote.fields.alignment'))
                    ->options([
                        'start' => __('mipress::admin.mason_bricks.pull_quote.options.alignment_start'),
                        'center' => __('mipress::admin.mason_bricks.pull_quote.options.alignment_center'),
                    ])
                    ->default('center'),
            ]);
    }
}
