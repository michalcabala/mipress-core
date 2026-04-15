<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\GlobalSetResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GlobalSetForm
{
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('mipress::admin.resources.global_set.form.sections.basic_information'))->schema([
                TextInput::make('title')
                    ->label(__('mipress::admin.resources.global_set.form.fields.title'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('handle')
                    ->label(__('mipress::admin.resources.global_set.form.fields.handle'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->regex('/^[a-z0-9_]+$/')
                    ->helperText(__('mipress::admin.resources.global_set.form.help.handle'))
                    ->disabled(fn (?string $operation): bool => $operation === 'edit'),
            ]),
            Section::make(__('mipress::admin.resources.global_set.form.sections.data'))->schema([
                KeyValue::make('data')
                    ->label(__('mipress::admin.resources.global_set.form.fields.data'))
                    ->keyLabel(__('mipress::admin.resources.global_set.form.fields.key'))
                    ->valueLabel(__('mipress::admin.resources.global_set.form.fields.value'))
                    ->reorderable(),
            ]),
        ]);
    }
}
