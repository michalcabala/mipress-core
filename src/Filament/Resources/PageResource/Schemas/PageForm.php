<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Schemas;

use Awcodes\Mason\Enums\SidebarPosition;
use Awcodes\Mason\Mason;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Filament\Support\EntryLikeFormBuilders;
use MiPress\Core\Mason\EditorialBrickCollection;
use MiPress\Core\Models\Page;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        $record = $schema->getRecord();
        $isEdit = $record instanceof Page;

        $components = [];
        $seoSection = EntryLikeFormBuilders::makeSeoSection('stránky');

        $components[] =
            Grid::make([
                'default' => 1,
                'lg' => 4,
            ])->columnSpanFull()
                ->disabled(fn (): bool => $record instanceof Page ? EntryLikeFormBuilders::isReadOnlyForCurrentUser($record) : false)
                ->schema([
                    Grid::make(1)
                        ->columnSpan(['default' => 1, 'lg' => 3])
                        ->schema([
                            Section::make('Základ')
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
                                ]),

                            Section::make('Obsah')
                                ->icon('fal-file-lines')
                                ->schema([
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
                            Section::make('Publikace')
                                ->icon('fal-calendar')
                                ->schema(EntryLikeFormBuilders::makePublicationFields(
                                    $record,
                                    [
                                    Select::make('parent_id')
                                        ->label('Nadřazená stránka')
                                        ->options(fn (): array => self::getParentOptions($record))
                                        ->searchable()
                                        ->preload()
                                        ->native(false)
                                        ->nullable()
                                        ->helperText('Vyberte nadřazenou stránku pro vytvoření hierarchie.'),
                                    ],
                                )),

                            EntryLikeFormBuilders::makeFeaturedImageSection(),

                            Section::make('Stav')
                                ->visible($isEdit)
                                ->schema([
                                    ...EntryLikeFormBuilders::makeStatusOverviewEntries('published_status_at'),

                                    Actions::make([
                                        Action::make('moveToTrash')
                                            ->label('Přesunout do koše')
                                            ->icon('far-trash-can')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->modalHeading(fn (Page $record): string => 'Přesunout stránku "'.$record->title.'" do koše?')
                                            ->modalDescription('Stránka nebude trvale smazána a bude ji možné obnovit z koše.')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $record->delete();
                                                Notification::make()
                                                    ->title('Stránka byla přesunuta do koše')
                                                    ->body('Stránka "'.$record->title.'" byla přesunuta do koše.')
                                                    ->success()
                                                    ->send();

                                                $livewire->redirect(PageResource::getUrl('index'));
                                            }),

                                        Action::make('deletePermanently')
                                            ->label('Smazat trvale')
                                            ->icon('far-trash-xmark')
                                            ->color('danger')
                                            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                                            ->requiresConfirmation()
                                            ->modalHeading(fn (Page $record): string => 'Trvale smazat stránku "'.$record->title.'"?')
                                            ->modalDescription('Tato akce stránku nevratně odstraní ze systému včetně jejího aktuálního stavu.')
                                            ->action(function (EditRecord $livewire, Page $record): void {
                                                $recordTitle = $record->title;
                                                $record->forceDelete();
                                                Notification::make()
                                                    ->title('Stránka byla trvale smazána')
                                                    ->body('Stránka "'.$recordTitle.'" byla ze systému odstraněna natrvalo.')
                                                    ->success()
                                                    ->send();

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
                                                $copy->status = ContentStatus::Draft;
                                                $copy->slug = null;
                                                $copy->published_at = null;
                                                $copy->review_note = null;
                                                $copy->save();

                                                Notification::make()
                                                    ->title('Kopie stránky byla vytvořena')
                                                    ->body('Nová stránka "'.$copy->title.'" vznikla ze stránky "'.$record->title.'".')
                                                    ->success()
                                                    ->send();
                                                $livewire->redirect(PageResource::getUrl('edit', ['record' => $copy]));
                                            }),
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
        $locale = $record?->locale;

        $query = Page::query()
            ->orderBy('title')
            ->when(
                filled($locale),
                fn (Builder $builder): Builder => $builder->where('locale', $locale),
            );

        if ($record instanceof Page) {
            $query->whereKeyNot($record->getKey());
        }

        $pages = $query
            ->get(['id', 'title', 'parent_id'])
            ->groupBy(fn (Page $page): string => (string) ($page->parent_id ?? 0));

        return self::flattenParentOptions($pages);
    }

    /**
     * @param  Collection<string, Collection<int, Page>>  $groupedPages
     * @param  array<int, string>  $options
     * @return array<int, string>
     */
    private static function flattenParentOptions(
        Collection $groupedPages,
        int $parentId = 0,
        int $depth = 0,
        array $options = [],
    ): array {
        /** @var Collection<int, Page> $children */
        $children = $groupedPages->get((string) $parentId, collect());

        foreach ($children as $child) {
            $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
            $options[(int) $child->getKey()] = $prefix.$child->title;
            $options = self::flattenParentOptions($groupedPages, (int) $child->getKey(), $depth + 1, $options);
        }

        return $options;
    }
}
