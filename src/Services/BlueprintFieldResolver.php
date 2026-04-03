<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use MiPress\Core\Mason\EditorialBrickCollection;

class BlueprintFieldResolver
{
    /**
     * Resolve a single Blueprint field definition into a Filament form component.
     */
    public function resolve(array $fieldDefinition): mixed
    {
        $handle = $fieldDefinition['handle'] ?? null;
        $label = $fieldDefinition['label'] ?? $handle;
        $required = (bool) ($fieldDefinition['required'] ?? false);
        $config = $fieldDefinition['config'] ?? [];

        if (! $handle) {
            return null;
        }

        $component = match ($fieldDefinition['type'] ?? 'text') {
            'textarea' => Textarea::make($handle)
                ->label($label)
                ->rows($config['rows'] ?? 4),
            'number' => TextInput::make($handle)
                ->label($label)
                ->numeric()
                ->minValue($config['min'] ?? null)
                ->maxValue($config['max'] ?? null),
            'select' => Select::make($handle)
                ->label($label)
                ->options($config['options'] ?? [])
                ->multiple($config['multiple'] ?? false),
            'checkbox' => Checkbox::make($handle)->label($label),
            'toggle' => Toggle::make($handle)->label($label),
            'radio' => Radio::make($handle)
                ->label($label)
                ->options($config['options'] ?? []),
            'datetime' => DateTimePicker::make($handle)->label($label),
            'date' => DatePicker::make($handle)->label($label),
            'image', 'file' => CuratorPicker::make($handle)->label($label),
            'color' => ColorPicker::make($handle)->label($label),
            'tags' => TagsInput::make($handle)->label($label),
            'repeater' => Repeater::make($handle)
                ->label($label)
                ->schema([
                    TextInput::make('value')->label('Hodnota'),
                ])
                ->addActionLabel('Přidat záznam'),
            'keyvalue' => KeyValue::make($handle)->label($label),
            'richtext' => RichEditor::make($handle)->label($label)->columnSpanFull(),
            'markdown' => MarkdownEditor::make($handle)->label($label)->columnSpanFull(),
            'mason' => Mason::make($handle)
                ->label($label)
                ->bricks(EditorialBrickCollection::make())
                ->previewLayout('layouts.mason-preview')
                ->colorModeToggle()
                ->defaultColorMode('light')
                ->doubleClickToEdit()
                ->displayActionsAsGrid()
                ->sortBricks()
                ->sidebarPosition(SidebarPosition::End)
                ->extraInputAttributes(['style' => 'min-height: 42rem;'])
                ->columnSpanFull(),
            'hidden' => Hidden::make($handle),
            default => TextInput::make($handle)
                ->label($label)
                ->maxLength($config['maxLength'] ?? 255)
                ->placeholder($config['placeholder'] ?? null),
        };

        if ($required && method_exists($component, 'required')) {
            $component->required();
        }

        return $component;
    }

    /**
     * Resolve all Blueprint fields into ordered Filament Section components.
     * Handles both flat field arrays and nested section→fields structures.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, Section>
     */
    public function resolveAll(array $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        // Detect structure: flat field array vs nested section→fields array
        $firstItem = $fields[0] ?? [];

        if (isset($firstItem['section'])) {
            return $this->resolveNestedSections($fields);
        }

        return $this->resolveFlatFields($fields);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, Section>
     */
    protected function resolveNestedSections(array $sections): array
    {
        $result = [];

        foreach ($sections as $sectionDef) {
            $sectionFields = [];

            foreach ($sectionDef['fields'] ?? [] as $fieldDef) {
                $component = $this->resolve($fieldDef);

                if ($component !== null) {
                    $sectionFields[] = $component;
                }
            }

            if (! empty($sectionFields)) {
                $result[] = Section::make($sectionDef['section'] ?? 'Pole')
                    ->statePath('data')
                    ->schema($sectionFields);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, Section>
     */
    protected function resolveFlatFields(array $fields): array
    {
        $sorted = collect($fields)
            ->sortBy(fn (array $f): int => (int) ($f['order'] ?? 0))
            ->values()
            ->all();

        $components = [];

        foreach ($sorted as $fieldDef) {
            $component = $this->resolve($fieldDef);

            if ($component !== null) {
                $components[] = $component;
            }
        }

        if (empty($components)) {
            return [];
        }

        return [
            Section::make('Pole')
                ->statePath('data')
                ->schema($components),
        ];
    }
}
