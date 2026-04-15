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
        return __('mipress::admin.mason_bricks.narrative.label');
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
                    ->label(__('mipress::admin.mason_bricks.narrative.fields.eyebrow'))
                    ->maxLength(80),
                TextInput::make('heading')
                    ->label(__('mipress::admin.mason_bricks.narrative.fields.heading'))
                    ->maxLength(140),
                Select::make('tone')
                    ->label(__('mipress::admin.mason_bricks.narrative.fields.tone'))
                    ->options([
                        'default' => __('mipress::admin.mason_bricks.narrative.options.tone_default'),
                        'muted' => __('mipress::admin.mason_bricks.narrative.options.tone_muted'),
                        'accent' => __('mipress::admin.mason_bricks.narrative.options.tone_accent'),
                    ])
                    ->default('default'),
                Select::make('width')
                    ->label(__('mipress::admin.mason_bricks.narrative.fields.width'))
                    ->options([
                        'narrow' => __('mipress::admin.mason_bricks.narrative.options.width_narrow'),
                        'wide' => __('mipress::admin.mason_bricks.narrative.options.width_wide'),
                    ])
                    ->default('wide'),
                RichEditor::make('content')
                    ->label(__('mipress::admin.mason_bricks.narrative.fields.content'))
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
