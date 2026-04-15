<?php

declare(strict_types=1);

namespace MiPress\Core\Mason\Bricks;

use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

class CallToActionBrick extends Brick
{
    public static function getId(): string
    {
        return 'call-to-action';
    }

    public static function getLabel(): string
    {
        return __('mipress::admin.mason_bricks.call_to_action.label');
    }

    public static function getIcon(): string
    {
        return 'fal-bolt';
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        return view('mipress::mason.bricks.call-to-action.index', [
            'eyebrow' => $config['eyebrow'] ?? null,
            'title' => $config['title'] ?? null,
            'text' => $config['text'] ?? null,
            'primary_label' => $config['primary_label'] ?? null,
            'primary_url' => $config['primary_url'] ?? null,
            'secondary_label' => $config['secondary_label'] ?? null,
            'secondary_url' => $config['secondary_url'] ?? null,
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                TextInput::make('eyebrow')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.eyebrow')),
                TextInput::make('title')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.title'))
                    ->required(),
                Textarea::make('text')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.text'))
                    ->rows(4),
                TextInput::make('primary_label')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.primary_label')),
                TextInput::make('primary_url')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.primary_url'))
                    ->url(),
                TextInput::make('secondary_label')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.secondary_label')),
                TextInput::make('secondary_url')
                    ->label(__('mipress::admin.mason_bricks.call_to_action.fields.secondary_url'))
                    ->url(),
            ]);
    }
}
