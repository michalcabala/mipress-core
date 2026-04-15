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
use MiPress\Core\Enums\ContentStatus;
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
                ->label(__('mipress::admin.entry_like_form.meta_title'))
                ->maxLength(60)
                ->helperText(__('mipress::admin.entry_like_form.meta_title_helper', ['subject' => $titleFallbackSubject])),
            Textarea::make('meta_description')
                ->label(__('mipress::admin.entry_like_form.meta_description'))
                ->maxLength(160)
                ->rows(3)
                ->helperText(__('mipress::admin.entry_like_form.meta_description_helper')),
        ];

        if ($includeOgImage) {
            $schema[] = CuratorPicker::make('og_image_id')
                ->label(__('mipress::admin.entry_like_form.og_image'))
                ->helperText(__('mipress::admin.entry_like_form.og_image_helper'));
        }

        $section = Section::make(__('mipress::admin.entry_like_form.seo_section'))
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
        return Section::make(__('mipress::admin.entry_like_form.featured_image_section'))
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
                ->label(__('mipress::admin.entry_like_form.author'))
                ->relationship('author', 'name')
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->default(fn () => auth()->id()),
            TextInput::make('sort_order')
                ->label(__('mipress::admin.entry_like_form.sort_order'))
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
                ->label(__('mipress::admin.entry_like_form.publication_status'))
                ->state(fn (Entry|Page $record): HtmlString => self::renderStatusBadge($record->status)),

            TextEntry::make('status_meta')
                ->label(__('mipress::admin.entry_like_form.status_detail'))
                ->visible(fn (Entry|Page $record): bool => self::renderStatusMeta($record) !== '')
                ->state(fn (Entry|Page $record): HtmlString => new HtmlString(self::renderStatusMeta($record))),

            TextEntry::make($publishedDateFieldName)
                ->label(__('mipress::admin.entry_like_form.publication_date'))
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
            && $record->status === ContentStatus::InReview;
    }

    public static function formatPublicationDate(Entry|Page $record): string
    {
        $publicationAt = $record->scheduled_at ?? $record->published_at;

        return $publicationAt?->format('j. n. Y H:i') ?? '—';
    }

    public static function renderStatusBadge(ContentStatus $status): HtmlString
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
            ContentStatus::Published => __('mipress::admin.entry_like_form.status_meta.published'),
            ContentStatus::Rejected => __('mipress::admin.entry_like_form.status_meta.rejected', ['reason' => e($record->review_note ?? '—')]),
            ContentStatus::Scheduled => __('mipress::admin.entry_like_form.status_meta.scheduled', ['date' => e($scheduledAt?->format('j. n. Y H:i') ?? '—')]),
            ContentStatus::InReview => __('mipress::admin.entry_like_form.status_meta.in_review'),
            default => '',
        };
    }

    private static function makePublicationStatusField(Entry|Page|null $record, bool $canPublish): ToggleButtons
    {
        return self::configureReactivePublicationStatusField(
            ToggleButtons::make('status')
                ->label(__('mipress::admin.entry_like_form.publication_state'))
                ->options(self::getPublicationStatusOptions($record, $canPublish))
                ->colors(self::getPublicationStatusColors())
                ->icons(self::getPublicationStatusIcons())
                ->inline()
                ->required()
                ->default(ContentStatus::Draft->value)
                ->helperText(self::publicationStatusHelperText($record, $canPublish)),
            $canPublish,
        );
    }

    private static function makePublicationDateField(bool $canPublish): DateTimePicker
    {
        return self::configureReactivePublicationDateField(
            DateTimePicker::make('published_at')
                ->label(__('mipress::admin.entry_like_form.publication_date_field'))
                ->nullable()
                ->disabled(fn (): bool => ! $canPublish)
                ->helperText(__('mipress::admin.entry_like_form.publication_date_helper')),
            $canPublish,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function getPublicationStatusOptions(Entry|Page|null $record, bool $canPublish): array
    {
        return collect(self::getVisiblePublicationStatuses($record, $canPublish))
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }

    /**
     * @return array<int, ContentStatus>
     */
    private static function getVisiblePublicationStatuses(Entry|Page|null $record, bool $canPublish): array
    {
        if ($canPublish) {
            return ContentStatus::cases();
        }

        if ($record === null) {
            return [ContentStatus::Draft, ContentStatus::InReview];
        }

        return match ($record->status) {
            ContentStatus::Published, ContentStatus::Scheduled => [$record->status, ContentStatus::InReview],
            ContentStatus::Rejected => [$record->status, ContentStatus::Draft, ContentStatus::InReview],
            default => [ContentStatus::Draft, ContentStatus::InReview],
        };
    }

    /**
     * @return array<string, string|array|null>
     */
    private static function getPublicationStatusColors(): array
    {
        return collect(ContentStatus::cases())
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getColor()])
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private static function getPublicationStatusIcons(): array
    {
        return collect(ContentStatus::cases())
            ->mapWithKeys(fn (ContentStatus $status): array => [$status->value => $status->getIcon()])
            ->all();
    }

    private static function publicationStatusHelperText(Entry|Page|null $record, bool $canPublish): string
    {
        if ($canPublish) {
            return __('mipress::admin.entry_like_form.publication_helper.can_publish');
        }

        if ($record !== null && in_array($record->status, [ContentStatus::Published, ContentStatus::Scheduled], true)) {
            return __('mipress::admin.entry_like_form.publication_helper.needs_review_after_publish');
        }

        return __('mipress::admin.entry_like_form.publication_helper.choose_state');
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
