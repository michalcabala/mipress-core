<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;

class EntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $collection = EntryResource::getCurrentCollection();

        if (! $collection) {
            $record = $schema->getRecord();

            if ($record instanceof Entry && $record->collection_id) {
                $collection = $record->load('collection.blueprint')->collection;
            }
        }

        $blueprint = $collection?->blueprint;
        $hasSlug = (bool) ($collection?->slugs);
        $record = $schema->getRecord();
        $isEdit = $record instanceof Entry;
        $recordId = $schema->getRecord()?->id;

        $uniqueSlugRule = $collection
            ? Rule::unique('entries', 'slug')
                ->where('collection_id', $collection->id)
                ->ignore($recordId)
            : null;

        $components = [
            Grid::make([
                'default' => 1,
                'lg' => 3,
            ])->schema([
                Grid::make(1)
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('Obsah')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('title')
                                        ->label('Titulek')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                            if (($get('slug') ?? '') !== Str::slug($old)) {
                                                return;
                                            }

                                            $set('slug', Str::slug($state));
                                        })
                                        ->columnSpan($hasSlug ? 1 : 2),

                                    TextInput::make('slug')
                                        ->label('Slug')
                                        ->required($hasSlug)
                                        ->visible($hasSlug)
                                        ->maxLength(200)
                                        ->rules(array_filter([
                                            'alpha_dash',
                                            $uniqueSlugRule,
                                        ])),
                                ]),
                                ...self::buildBlueprintSections($blueprint),
                            ]),

                        Section::make('SEO')
                            ->icon('heroicon-o-magnifying-glass')
                            ->collapsible()
                            ->collapsed()
                            ->statePath('data')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('SEO titulek')
                                    ->maxLength(60),
                                Textarea::make('meta_description')
                                    ->label('SEO popis')
                                    ->maxLength(160)
                                    ->rows(3),
                                FileUpload::make('featured_image')
                                    ->label('Hlavní obrázek')
                                    ->image()
                                    ->disk('public')
                                    ->directory('entries')
                                    ->imageEditor()
                                    ->imageEditorAspectRatioOptions([
                                        '16:9' => '16:9',
                                        '4:3' => '4:3',
                                        '1:1' => '1:1',
                                        '1.91:1' => '1.91:1',
                                    ])
                                    ->visibility('public')
                                    ->nullable(),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Section::make('Workflow')
                            ->visible($isEdit)
                            ->schema([
                                Actions::make([
                                    Action::make('saveAndPublish')
                                        ->label('Uložit a publikovat')
                                        ->icon('far-circle-check')
                                        ->color('success')
                                        ->visible(fn (): bool => (bool) auth()->user()?->can('entry.publish'))
                                        ->requiresConfirmation()
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $livewire->save(false, false);

                                            $record->refresh();
                                            $oldStatus = $record->status;
                                            $record->status = EntryStatus::Published;
                                            $record->published_at ??= now();
                                            $record->review_note = null;
                                            $record->save();

                                            AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);
                                            Notification::make()->title('Položka publikována')->success()->send();
                                        }),

                                    Action::make('saveDraft')
                                        ->label('Uložit koncept')
                                        ->icon('far-floppy-disk')
                                        ->color('gray')
                                        ->visible(fn (): bool => ! auth()->user()?->can('entry.publish'))
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $livewire->save(false, false);

                                            $record->refresh();
                                            $oldStatus = $record->status;
                                            $record->status = EntryStatus::Draft;
                                            $record->save();

                                            AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);
                                            Notification::make()->title('Koncept uložen')->success()->send();
                                        }),

                                    Action::make('submitForReview')
                                        ->label('Odeslat ke schválení')
                                        ->icon('far-paper-plane')
                                        ->color('info')
                                        ->visible(fn (Entry $record): bool => in_array($record->status, [EntryStatus::Draft, EntryStatus::Rejected], true)
                                            && ! auth()->user()?->can('entry.publish'))
                                        ->requiresConfirmation()
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $livewire->save(false, false);

                                            $record->refresh();
                                            $oldStatus = $record->status;
                                            $record->status = EntryStatus::InReview;
                                            $record->save();

                                            AuditLog::logStatusChange($record, EntryStatus::InReview, $oldStatus);
                                            Notification::make()->title('Odesláno ke schválení')->success()->send();
                                        }),
                                ])->fullWidth(),
                            ]),

                        Section::make('Stav')
                            ->visible($isEdit)
                            ->schema([
                                Placeholder::make('status_badge')
                                    ->label('Aktuální stav')
                                    ->content(fn (Entry $record): string => $record->status->getLabel()),

                                Placeholder::make('published_status_at')
                                    ->label('Datum publikování')
                                    ->visible(fn (Entry $record): bool => $record->status === EntryStatus::Published && filled($record->published_at))
                                    ->content(fn (Entry $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),

                                Placeholder::make('review_note_notice')
                                    ->label('Důvod zamítnutí')
                                    ->visible(fn (Entry $record): bool => $record->status === EntryStatus::Rejected && filled($record->review_note))
                                    ->content(fn (Entry $record): string => $record->review_note ?? ''),

                                Actions::make([
                                    Action::make('unpublish')
                                        ->label('Unpublish')
                                        ->icon('far-eye-slash')
                                        ->color('gray')
                                        ->visible(fn (Entry $record): bool => $record->status === EntryStatus::Published)
                                        ->requiresConfirmation()
                                        ->action(function (Entry $record): void {
                                            $oldStatus = $record->status;
                                            $record->status = EntryStatus::Draft;
                                            $record->save();

                                            AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);
                                            Notification::make()->title('Položka vrácena do konceptu')->success()->send();
                                        }),

                                    Action::make('reject')
                                        ->label('Zamítnout')
                                        ->icon('far-circle-xmark')
                                        ->color('danger')
                                        ->visible(fn (Entry $record): bool => $record->status === EntryStatus::InReview
                                            && auth()->user()?->can('entry.publish'))
                                        ->schema([
                                            Textarea::make('reason')
                                                ->label('Důvod zamítnutí')
                                                ->required()
                                                ->rows(3),
                                        ])
                                        ->action(function (array $data, Entry $record): void {
                                            $oldStatus = $record->status;
                                            $record->status = EntryStatus::Rejected;
                                            $record->review_note = $data['reason'];
                                            $record->save();

                                            AuditLog::logStatusChange($record, EntryStatus::Rejected, $oldStatus, $data['reason']);
                                            Notification::make()->title('Položka zamítnuta')->warning()->send();
                                        }),

                                    Action::make('moveToTrash')
                                        ->label('Přesunout do koše')
                                        ->icon('far-trash-can')
                                        ->color('warning')
                                        ->requiresConfirmation()
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $record->delete();
                                            Notification::make()->title('Položka přesunuta do koše')->success()->send();

                                            $livewire->redirect(EntryResource::getUrl('index', [
                                                'collection' => $record->collection?->handle,
                                            ]));
                                        }),

                                    Action::make('deletePermanently')
                                        ->label('Smazat trvale')
                                        ->icon('far-trash-xmark')
                                        ->color('danger')
                                        ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                        ->requiresConfirmation()
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $record->forceDelete();
                                            Notification::make()->title('Položka byla trvale smazána')->success()->send();

                                            $livewire->redirect(EntryResource::getUrl('index'));
                                        }),

                                    Action::make('duplicate')
                                        ->label('Duplikovat')
                                        ->icon('far-copy')
                                        ->color('info')
                                        ->action(function (EditRecord $livewire, Entry $record): void {
                                            $copy = $record->replicate();
                                            $copy->title = str($record->title)->append(' (kopie)')->toString();
                                            $copy->status = EntryStatus::Draft;
                                            $copy->slug = null;
                                            $copy->published_at = null;
                                            $copy->review_note = null;
                                            $copy->save();

                                            Notification::make()->title('Kopie vytvořena')->success()->send();
                                            $livewire->redirect(EntryResource::getUrl('edit', ['record' => $copy]));
                                        }),

                                    Action::make('history')
                                        ->label('Historie')
                                        ->icon('far-clock-rotate-left')
                                        ->url(fn (Entry $record): string => EntryResource::getUrl('history', ['record' => $record])),
                                ])->fullWidth(),
                            ]),

                        Section::make('Detaily obsahu')
                            ->visible($isEdit)
                            ->schema([
                                Placeholder::make('entry_id')
                                    ->label('ID')
                                    ->content(fn (Entry $record): string => (string) $record->id),
                                Placeholder::make('created_info')
                                    ->label('Vytvořeno')
                                    ->content(fn (Entry $record): string => ($record->created_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                Placeholder::make('updated_info')
                                    ->label('Upraveno')
                                    ->content(fn (Entry $record): string => ($record->updated_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                Placeholder::make('published_info')
                                    ->label('Publikováno')
                                    ->content(fn (Entry $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),
                            ]),

                        Section::make('Nastavení')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Datum publikování')
                                    ->nullable()
                                    ->visible(fn (): bool => (bool) auth()->user()?->can('entry.publish')),
                                Select::make('author_id')
                                    ->label('Autor')
                                    ->relationship('author', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => auth()->id()),
                                TextInput::make('sort_order')
                                    ->label('Pořadí')
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ]),
            ]),
        ];

        if ($collection) {
            $components[] = Hidden::make('collection_id')
                ->default($collection->id);
        }

        return $schema->components($components);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }

    /**
     * @return array<int, Section>
     */
    protected static function buildBlueprintSections(?object $blueprint): array
    {
        if (! $blueprint || empty($blueprint->fields)) {
            return [];
        }

        $sections = [];

        foreach ($blueprint->fields as $sectionDef) {
            $fields = [];

            foreach ($sectionDef['fields'] ?? [] as $fieldDef) {
                $component = self::buildFieldComponent($fieldDef);

                if ($component) {
                    $fields[] = $component;
                }
            }

            if (! empty($fields)) {
                $sections[] = Section::make($sectionDef['section'] ?? 'Pole')
                    ->statePath('data')
                    ->schema($fields);
            }
        }

        return $sections;
    }

    protected static function buildFieldComponent(array $fieldDef): mixed
    {
        $handle = $fieldDef['handle'] ?? null;
        $label = $fieldDef['label'] ?? $handle;
        $required = (bool) ($fieldDef['required'] ?? false);

        if (! $handle) {
            return null;
        }

        $component = match ($fieldDef['type'] ?? 'text') {
            'textarea' => Textarea::make($handle)->label($label)->rows(4),
            'number' => TextInput::make($handle)->label($label)->numeric(),
            'select' => Select::make($handle)->label($label)->options($fieldDef['options'] ?? []),
            'checkbox' => Checkbox::make($handle)->label($label),
            'toggle' => Toggle::make($handle)->label($label),
            'radio' => Radio::make($handle)->label($label)->options($fieldDef['options'] ?? []),
            'datetime' => DateTimePicker::make($handle)->label($label),
            'date' => DatePicker::make($handle)->label($label),
            'media' => TextInput::make($handle)->label($label)->helperText('Curator bude přidán'),
            'color' => ColorPicker::make($handle)->label($label),
            'tags' => TagsInput::make($handle)->label($label),
            'repeater' => Repeater::make($handle)->label($label)->schema([
                TextInput::make('value')->label('Hodnota'),
            ])->addActionLabel('Přidat záznam'),
            'keyvalue' => KeyValue::make($handle)->label($label),
            'markdown' => MarkdownEditor::make($handle)->label($label),
            'mason' => TextInput::make($handle)->label($label)->helperText('Mason bricks budou definovány'),
            default => TextInput::make($handle)->label($label)->maxLength(255),
        };

        if ($required) {
            $component->required();
        }

        return $component;
    }
}
