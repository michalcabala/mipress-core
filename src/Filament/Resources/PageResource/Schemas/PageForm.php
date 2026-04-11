<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Page;

use function Filament\Support\generate_icon_html;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $isEdit = $record instanceof Page;

        $components = [];
        $seoSection = Section::make('SEO')
            ->icon('fal-magnifying-glass')
            ->collapsible()
            ->schema([
                TextInput::make('meta_title')
                    ->label('SEO titulek')
                    ->maxLength(60)
                    ->helperText('Doporučeno 50-60 znaků. Pokud zůstane prázdný, použije se titulek stránky.'),
                Textarea::make('meta_description')
                    ->label('SEO popis')
                    ->maxLength(160)
                    ->rows(3)
                    ->helperText('Krátký popis pro výsledky vyhledávání a sdílení.'),
            ]);

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
                                ->icon('fal-file-lines')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('title')
                                            ->label('Titulek')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Např. O nás')
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
                                            ->placeholder('o-nas')
                                            ->helperText('Používá se v URL stránky.')
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
                            ...($isEdit ? [] : [$seoSection]),
                        ]),

                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 1])
                        ->schema([
                            Section::make('Stav')
                                ->visible($isEdit)
                                ->schema([
                                    TextEntry::make('status_badge')
                                        ->label('Aktuální stav')
                                        ->state(fn (Page $record): HtmlString => self::renderStatusBadge($record->status)),

                                    TextEntry::make('published_status_at')
                                        ->label('Datum publikování')
                                        ->visible(fn (Page $record): bool => $record->status === EntryStatus::Published && filled($record->published_at))
                                        ->state(fn (Page $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),

                                    TextEntry::make('review_note_notice')
                                        ->label('Důvod zamítnutí')
                                        ->visible(fn (Page $record): bool => $record->status === EntryStatus::Rejected && filled($record->review_note))
                                        ->state(fn (Page $record): string => $record->review_note ?? ''),

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
                                    TextEntry::make('page_id')
                                        ->label('ID')
                                        ->state(fn (Page $record): string => (string) $record->id),
                                    TextEntry::make('created_info')
                                        ->label('Vytvořeno')
                                        ->state(fn (Page $record): string => ($record->created_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('updated_info')
                                        ->label('Upraveno')
                                        ->state(fn (Page $record): string => ($record->updated_at?->format('j. n. Y H:i') ?? '—').' — '.($record->author?->name ?? '—')),
                                    TextEntry::make('published_info')
                                        ->label('Publikováno')
                                        ->state(fn (Page $record): string => $record->published_at?->format('j. n. Y H:i') ?? '—'),
                                ]),

                            Section::make('Nastavení')
                                ->icon('fal-gear')
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
                                        ->native(false)
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
        $badge = self::renderStatusBadge($record->status)->toHtml();
        $meta = self::renderStatusMeta($record);

        return new HtmlString(
            '<div style="display:flex;align-items:flex-start;gap:12px;padding:10px 0;">'
            .$badge
            .'<div style="font-size:14px;line-height:1.5;color:#374151;">'.$meta.'</div>'
            .'</div>'
        );
    }

    private static function renderStatusBadge(EntryStatus $status): HtmlString
    {
        $color = $status->getColor();
        $color = is_string($color) ? $color : 'gray';
        $icon = generate_icon_html(
            $status->getIcon(),
            attributes: new ComponentAttributeBag(['class' => 'shrink-0']),
            size: IconSize::Small,
        )?->toHtml() ?? '';

        return new HtmlString(
            '<span class="fi-badge fi-color-'.e($color).' fi-size-sm">'
            .'<span class="inline-flex items-center gap-1.5">'.$icon.'<span>'.e($status->getLabel()).'</span></span>'
            .'</span>'
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
        $scheduledAt = $record->scheduled_at ?? $record->published_at;

        return match ($record->status) {
            EntryStatus::Published => 'Publikováno · schválil '.$actor.' · '.$date,
            EntryStatus::Rejected => 'Zamítnuto · zamítl '.$actor.' · '.$date.'<br><strong>Důvod:</strong> '.e($record->review_note ?? '—'),
            EntryStatus::Scheduled => 'Naplánováno na '.e($scheduledAt?->format('j. n. Y H:i') ?? '—').' · naplánoval '.$actor,
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
}
