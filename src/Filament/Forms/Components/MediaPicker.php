<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TableSelect;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\IconPosition;
use Illuminate\Database\Eloquent\Collection;
use MiPress\Core\Filament\Tables\PickerMediaTable;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Media;
use MiPress\Core\Services\MediaLibraryService;

class MediaPicker extends Field
{
    protected string $view = 'mipress::filament.forms.components.media-picker';

    protected bool $isMultiple = false;

    protected bool $isReorderable = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            fn (MediaPicker $component): Action => $component->getManageMediaAction(),
        ]);

        $this->afterStateHydrated(function (MediaPicker $component, mixed $state): void {
            if ($component->isMultiple() && ! is_array($state)) {
                $component->state([]);
            }
        });
    }

    public function single(): static
    {
        $this->isMultiple = false;

        return $this;
    }

    public function multiple(): static
    {
        $this->isMultiple = true;

        return $this;
    }

    public function reorderable(bool $condition = true): static
    {
        $this->isReorderable = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->isMultiple;
    }

    public function isReorderable(): bool
    {
        return $this->isReorderable;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getSelectedMedia(): Collection
    {
        $selectedIds = collect($this->getState())
            ->when(
                ! $this->isMultiple(),
                fn ($collection) => collect([$this->getState()]),
            )
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($selectedIds->isEmpty()) {
            return new Collection;
        }

        $mediaItems = Media::query()
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        return new Collection(
            $selectedIds
                ->map(fn (int $id): ?Media => $mediaItems->get($id))
                ->filter()
                ->values()
                ->all(),
        );
    }

    /**
     * @return list<array{id: int, name: string, url: ?string}>
     */
    public function getSelectedMediaData(): array
    {
        return $this->getSelectedMedia()
            ->map(fn (Media $media): array => [
                'id' => (int) $media->getKey(),
                'name' => $media->file_name,
                'url' => $media->isImage() ? mipress_media_url($media, 'thumbnail') : null,
            ])
            ->all();
    }

    public function getManageMediaAction(): Action
    {
        return Action::make('manageMedia')
            ->label($this->isMultiple() ? 'Vybrat média' : 'Vybrat médium')
            ->icon('fal-photo-film')
            ->iconPosition(IconPosition::After)
            ->slideOver()
            ->modalWidth('7xl')
            ->modalHeading($this->isMultiple() ? 'Výběr médií' : 'Výběr média')
            ->modalSubmitActionLabel('Potvrdit výběr')
            ->fillForm([
                'selection' => $this->getState(),
                'uploads' => [],
            ])
            ->schema([
                Tabs::make('media_tabs')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Knihovna médií')
                            ->schema([
                                TableSelect::make('selection')
                                    ->hiddenLabel()
                                    ->tableConfiguration(PickerMediaTable::class)
                                    ->multiple($this->isMultiple()),
                            ]),
                        Tab::make('Nahrát nové')
                            ->schema([
                                FileUpload::make('uploads')
                                    ->hiddenLabel()
                                    ->disk(MediaConfig::disk())
                                    ->directory('tmp/picker')
                                    ->visibility('public')
                                    ->multiple($this->isMultiple())
                                    ->preserveFilenames()
                                    ->imageEditor()
                                    ->imageEditorMode(2)
                                    ->imageEditorAspectRatioOptions([
                                        null,
                                        '1:1',
                                        '4:3',
                                        '16:9',
                                        '1200:630',
                                    ])
                                    ->acceptedFileTypes(MediaConfig::allowedMimeTypes())
                                    ->maxSize((int) floor(MediaConfig::maxUploadSize() / 1024))
                                    ->helperText('Po uložení se soubory přesunou do knihovny médií. U obrázků je dostupný editor ořezu a rotace.'),
                            ]),
                    ]),
            ])
            ->action(function (array $data): void {
                $selectedIds = collect($data['selection'] ?? [])
                    ->when(
                        ! $this->isMultiple(),
                        fn ($collection) => collect([$data['selection'] ?? null]),
                    )
                    ->filter(fn (mixed $id): bool => is_numeric($id))
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values();

                $uploadedIds = collect(app(MediaLibraryService::class)->createFromTemporaryPaths(
                    $data['uploads'] ?? [],
                    auth()->id(),
                ));

                $nextState = $this->isMultiple()
                    ? $selectedIds->merge($uploadedIds)->unique()->values()->all()
                    : ($uploadedIds->last() ?? $selectedIds->last());

                $this->state($nextState);
                $this->callAfterStateUpdated();
            });
    }
}
