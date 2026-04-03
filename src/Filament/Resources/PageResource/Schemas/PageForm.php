<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Page;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $isEdit = $record instanceof Page;

        $components = [];

        if ($isEdit) {
            $components[] = Section::make()
                ->compact()
                ->schema([
                    Placeholder::make('status_overview')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => self::renderStatusOverview($record)),
                ]);
        }

        $components[] =
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Page ? self::isReadOnlyForCurrentUser($record) : false)
                ->schema([
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 3])
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
                                            }),

                                        TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(200)
                                            ->rules(['alpha_dash']),
                                    ]),
                                    Mason::make('content')
                                        ->label('Obsah')
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
                                ]),

                            Section::make('SEO')
                                ->icon('heroicon-o-magnifying-glass')
                                ->collapsible()
                                ->schema([
                                    TextInput::make('meta_title')
                                        ->label('SEO titulek')
                                        ->maxLength(60),
                                    Textarea::make('meta_description')
                                        ->label('SEO popis')
                                        ->maxLength(160)
                                        ->rows(3),
                                ]),
                        ]),

                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 1])
                        ->schema([
                            Section::make('Stav')
                                ->visible($isEdit)
                                ->schema([
                                    Placeholder::make('status_badge')
                                        ->label('Aktuální stav')
                                        ->content(fn (Page $record): HtmlString => new HtmlString('<span class="fi-badge fi-color-gray fi-size-sm">'.e($record->status->getLabel()).'</span>')),

                                    Placeholder::make('published_status_at')
                                        ->label('Datum publikování')
                                        ->visible(fn (Page $record): bool => $record->status === EntryStatus::Published && filled($record->published_at))
                                        ->content(fn (Page $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),

                                    Placeholder::make('review_note_notice')
                                        ->label('Důvod zamítnutí')
                                        ->visible(fn (Page $record): bool => $record->status === EntryStatus::Rejected && filled($record->review_note))
                                        ->content(fn (Page $record): string => $record->review_note ?? ''),

                                    Actions::make([
                                        Action::make('moveToTrash')
                                            ->label('Přesunout do koše')
                                            ->icon('far-trash-can')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $record->delete();
                                                Notification::make()->title('Stránka přesunuta do koše')->success()->send();

                                                $livewire->redirect(PageResource::getUrl('index'));
                                            }),

                                        Action::make('deletePermanently')
                                            ->label('Smazat trvale')
                                            ->icon('far-trash-xmark')
                                            ->color('danger')
                                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                            ->requiresConfirmation()
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $record->forceDelete();
                                                Notification::make()->title('Stránka byla trvale smazána')->success()->send();

                                                $livewire->redirect(PageResource::getUrl('index'));
                                            }),
                                    ])->fullWidth(),

                                    Actions::make([
                                        Action::make('duplicate')
                                            ->label('Duplikovat')
                                            ->icon('far-copy')
                                            ->color('info')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $copy = $record->replicate();
                                                $copy->title = str($record->title)->append(' (kopie)')->toString();
                                                $copy->status = EntryStatus::Draft;
                                                $copy->slug = null;
                                                $copy->published_at = null;
                                                $copy->review_note = null;
                                                $copy->save();

                                                Notification::make()->title('Kopie vytvořena')->success()->send();
                                                $livewire->redirect(PageResource::getUrl('edit', ['record' => $copy]));
                                            }),

                                        Action::make('history')
                                            ->label('Revize')
                                            ->icon('far-code-compare')
                                            ->url(fn (Page $record): string => PageResource::getUrl('history', ['record' => $record])),
                                    ])->fullWidth(),
                                ]),

                            Section::make('Detaily stránky')
                                ->visible($isEdit)
                                ->schema([
                                    Placeholder::make('page_id')
                                        ->label('ID')
                                        ->content(fn (Page $record): string => (string) $record->id),
                                    Placeholder::make('created_info')
                                        ->label('Vytvořeno')
                                        ->content(fn (Page $record): string => ($record->created_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    Placeholder::make('updated_info')
                                        ->label('Upraveno')
                                        ->content(fn (Page $record): string => ($record->updated_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    Placeholder::make('published_info')
                                        ->label('Publikováno')
                                        ->content(fn (Page $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),
                                ]),

                            Section::make('Hlavní obrázek')
                                ->icon('heroicon-o-photo')
                                ->schema([
                                    CuratorPicker::make('featured_image_id')
                                        ->relationship('featuredImage', 'id')
                                        ->label('')
                                        ->nullable(),
                                ]),

                            Section::make('Nastavení')
                                ->icon('heroicon-o-cog-6-tooth')
                                ->schema([
                                    Select::make('parent_id')
                                        ->label('Nadřazená stránka')
                                        ->options(fn (): array => self::getParentOptions($record))
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Vyberte nadřazenou stránku pro vytvoření hierarchie.'),
                                    DateTimePicker::make('published_at')
                                        ->label('Datum publikování')
                                        ->nullable()
                                        ->disabled(fn (): bool => ! ((bool) auth()->user()?->can('entry.publish')))
                                        ->helperText('Prázdné = publikovat ihned, budoucnost = naplánovat publikaci.'),
                                    DateTimePicker::make('scheduled_at')
                                        ->label('Naplánovat na')
                                        ->nullable()
                                        ->helperText('Datum a čas automatického zveřejnění.')
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
                ]);

        return $schema->components($components);
    }

    public static function form(Schema $schema): Schema
    {
        return static::configure($schema);
    }

    private static function resolveDefaultBlueprint(): ?Blueprint
    {
        return Blueprint::where('handle', 'page')->first();
    }

    /**
     * @return array<int, string>
     */
    private static function getParentOptions(?Page $record): array
    {
        $query = Page::query()->orderBy('title');

        if ($record instanceof Page) {
            $query->whereKeyNot($record->getKey());
        }

        return $query
            ->get(['id', 'title'])
            ->pluck('title', 'id')
            ->all();
    }

    private static function renderStatusOverview(Page $record): HtmlString
    {
        $badgeColor = match ($record->status) {
            EntryStatus::Draft => '#6b7280',
            EntryStatus::InReview => '#d97706',
            EntryStatus::Published => '#16a34a',
            EntryStatus::Scheduled => '#2563eb',
            EntryStatus::Rejected => '#dc2626',
        };

        $label = e($record->status->getLabel());
        $meta = self::renderStatusMeta($record);

        return new HtmlString(
            '<div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;">'
            .'<span style="display:inline-flex;align-items:center;border-radius:9999px;padding:4px 10px;font-size:12px;font-weight:600;background:'.$badgeColor.';color:#fff;">'.$label.'</span>'
            .'<div style="font-size:14px;line-height:1.5;color:#374151;">'.$meta.'</div>'
            .'</div>'
        );
    }

    private static function renderStatusMeta(Page $record): string
    {
        $statusLog = AuditLog::query()
            ->with('user')
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey())
            ->where('action', 'status_changed')
            ->where('new_values->status', $record->status->value)
            ->latest('created_at')
            ->first();

        $actor = e($statusLog?->user?->name ?? 'Systém');
        $date = e($statusLog?->created_at?->format('j. n. Y H:i') ?? '—');

        return match ($record->status) {
            EntryStatus::Published => 'Publikováno · schválil '.$actor.' · '.$date,
            EntryStatus::Rejected => 'Zamítnuto · zamítl '.$actor.' · '.$date.'<br><strong>Důvod:</strong> '.e($record->review_note ?? '—'),
            EntryStatus::Scheduled => 'Naplánováno na '.e($record->published_at?->format('j. n. Y H:i') ?? '—').' · naplánoval '.$actor,
            EntryStatus::InReview => 'Odesláno ke schválení · odeslal '.$actor.' · '.$date,
            default => '',
        };
    }

    private static function isReadOnlyForCurrentUser(Page $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && $record->status === EntryStatus::InReview;
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
            'media' => CuratorPicker::make($handle)->label($label),
            'color' => ColorPicker::make($handle)->label($label),
            'tags' => TagsInput::make($handle)->label($label),
            'repeater' => Repeater::make($handle)->label($label)->schema([
                TextInput::make('value')->label('Hodnota'),
            ])->addActionLabel('Přidat záznam'),
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
            default => TextInput::make($handle)->label($label)->maxLength(255),
        };

        if ($required) {
            $component->required();
        }

        return $component;
    }
}
