<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Support;

use Awcodes\Curator\Components\Forms\CuratorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Forms\Components\UserSelect;
use MiPress\Core\Filament\Resources\Concerns\HasReactivePublicationFields;
use MiPress\Core\Models\Entry;
use MiPress\Core\Models\Page;

use function Filament\Support\generate_icon_html;

class EntryLikeFormBuilders
{
    use HasReactivePublicationFields;

    public static function makeSeoSection(
        string $titleFallbackSubject,
        bool $includeOgImage = false,
        bool $collapsible = true,
        bool $columnSpanFull = false,
    ): Section {
        $schema = [
            TextInput::make('meta_title')
                ->label('SEO titulek')
                ->maxLength(60)
                ->helperText('Doporučeno 50-60 znaků. Pokud zůstane prázdný, použije se titulek '.$titleFallbackSubject.'.'),
            Textarea::make('meta_description')
                ->label('SEO popis')
                ->maxLength(160)
                ->rows(3)
                ->helperText('Krátký popis pro výsledky vyhledávání a sdílení.'),
        ];

        if ($includeOgImage) {
            $schema[] = CuratorPicker::make('og_image_id')
                ->label('OG obrázek')
                ->helperText('Obrázek pro sdílení na sociálních sítích.');
        }

        $section = Section::make('SEO')
            ->icon('fal-magnifying-glass')
            ->schema($schema);

        if ($collapsible) {
            $section->collapsible();
        }

        if ($columnSpanFull) {
            $section->columnSpanFull();
        }

        return $section;
    }

    public static function makeFeaturedImageSection(): Section
    {
        return Section::make('Hlavní obrázek')
            ->icon('fal-image')
            ->schema([
                CuratorPicker::make('featured_image_id')
                    ->label(''),
            ]);
    }

    /**
     * @param  array<int, mixed>  $extraFields
     * @return array<int, mixed>
     */
    public static function makePublicationFields(Entry|Page|null $record, array $extraFields = []): array
    {
        $canPublish = self::canPublish($record);

        return [
            self::makePublicationStatusField($record, $canPublish),
            self::makePublicationDateField($canPublish),
            UserSelect::make('author_id')
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
            ...$extraFields,
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    public static function makeStatusOverviewEntries(string $publishedDateFieldName = 'published_info'): array
    {
        return [
            TextEntry::make('status_badge')
                ->label('Stav publikace')
                ->state(fn (Entry|Page $record): HtmlString => self::renderStatusBadge($record->status)),

            TextEntry::make('status_meta')
                ->label('Detail stavu')
                ->visible(fn (Entry|Page $record): bool => self::renderStatusMeta($record) !== '')
                ->state(fn (Entry|Page $record): HtmlString => new HtmlString(self::renderStatusMeta($record))),

            TextEntry::make($publishedDateFieldName)
                ->label('Datum publikace')
                ->state(fn (Entry|Page $record): string => self::formatPublicationDate($record)),
        ];
    }

    public static function isReadOnlyForCurrentUser(Entry|Page $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && $record->status === EntryStatus::InReview;
    }

    public static function formatPublicationDate(Entry|Page $record): string
    {
        $publicationAt = $record->scheduled_at ?? $record->published_at;

        return $publicationAt?->format('j. n. Y H:i') ?? '—';
    }

    public static function renderStatusBadge(EntryStatus $status): HtmlString
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

    public static function renderStatusMeta(Entry|Page $record): string
    {
        $scheduledAt = $record->scheduled_at ?? $record->published_at;

        return match ($record->status) {
            EntryStatus::Published => 'Publikováno',
            EntryStatus::Rejected => 'Zamítnuto<br><strong>Důvod:</strong> '.e($record->review_note ?? '—'),
            EntryStatus::Scheduled => 'Naplánováno na '.e($scheduledAt?->format('j. n. Y H:i') ?? '—'),
            EntryStatus::InReview => 'Odesláno ke schválení',
            default => '',
        };
    }

    private static function makePublicationStatusField(Entry|Page|null $record, bool $canPublish): ToggleButtons
    {
        return self::configureReactivePublicationStatusField(
            ToggleButtons::make('status')
                ->label('Stav publikování')
                ->options(self::getPublicationStatusOptions($record, $canPublish))
                ->colors(self::getPublicationStatusColors())
                ->icons(self::getPublicationStatusIcons())
                ->inline()
                ->required()
                ->default(EntryStatus::Draft->value)
                ->helperText(self::publicationStatusHelperText($record, $canPublish)),
            $canPublish,
        );
    }

    private static function makePublicationDateField(bool $canPublish): DateTimePicker
    {
        return self::configureReactivePublicationDateField(
            DateTimePicker::make('published_at')
                ->label('Datum publikace')
                ->nullable()
                ->disabled(fn (): bool => ! $canPublish)
                ->helperText('Pokud nastavíte budoucí datum a čas, obsah se uloží jako naplánovaný.'),
            $canPublish,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function getPublicationStatusOptions(Entry|Page|null $record, bool $canPublish): array
    {
        return collect(self::getVisiblePublicationStatuses($record, $canPublish))
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, EntryStatus>
     */
    private static function getVisiblePublicationStatuses(Entry|Page|null $record, bool $canPublish): array
    {
        if ($canPublish) {
            return EntryStatus::cases();
        }

        if ($record === null) {
            return [EntryStatus::Draft, EntryStatus::InReview];
        }

        return match ($record->status) {
            EntryStatus::Published, EntryStatus::Scheduled => [$record->status, EntryStatus::InReview],
            EntryStatus::Rejected => [$record->status, EntryStatus::Draft, EntryStatus::InReview],
            default => [EntryStatus::Draft, EntryStatus::InReview],
        };
    }

    /**
     * @return array<string, string|array|null>
     */
    private static function getPublicationStatusColors(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getColor()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private static function getPublicationStatusIcons(): array
    {
        return collect(EntryStatus::cases())
            ->mapWithKeys(fn (EntryStatus $status): array => [$status->value => $status->getIcon()])
            ->all();
    }

    private static function publicationStatusHelperText(Entry|Page|null $record, bool $canPublish): string
    {
        if ($canPublish) {
            return 'Budoucí datum a čas uloží obsah jako naplánovaný.';
        }

        if ($record !== null && in_array($record->status, [EntryStatus::Published, EntryStatus::Scheduled], true)) {
            return 'Po uložení budou změny odeslány ke schválení.';
        }

        return 'Vyberte, zda obsah uložit jako koncept nebo odeslat ke schválení.';
    }

    private static function canPublish(Entry|Page|null $record): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        if ($record !== null) {
            return $user->can('publish', $record);
        }

        return $user->hasPermissionTo('entry.publish');
    }
}
