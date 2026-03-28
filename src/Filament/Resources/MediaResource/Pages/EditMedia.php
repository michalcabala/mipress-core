<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\MediaResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use MiPress\Core\Filament\Resources\MediaResource;
use MiPress\Core\Models\Media;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected Width|string|null $maxWidth = Width::ExtraLarge;

    public function form(Schema $schema): Schema
    {
        /** @var Media $record */
        $record = $this->getRecord();

        return $schema->schema([
            Grid::make(['default' => 1, 'lg' => 3])->columnSpanFull()->schema([

                // Left: preview + focal point
                Section::make('Náhled')
                    ->columnSpan(['default' => 1, 'lg' => 1])
                    ->schema([
                        Placeholder::make('preview')
                            ->label('')
                            ->content(function () use ($record): HtmlString {
                                if ($record->isSvg()) {
                                    $url = e($record->getUrl());

                                    return new HtmlString("<img src=\"{$url}\" class=\"max-h-64 mx-auto\" alt=\"\" />");
                                }

                                if ($record->isImage() && $record->hasGeneratedConversion('medium')) {
                                    $url = e($record->getUrl('medium'));

                                    return new HtmlString("<img src=\"{$url}\" class=\"max-h-64 mx-auto rounded\" alt=\"\" />");
                                }

                                if ($record->isImage()) {
                                    $url = e($record->getUrl());

                                    return new HtmlString("<img src=\"{$url}\" class=\"max-h-64 mx-auto rounded\" alt=\"\" />");
                                }

                                return new HtmlString('<div class="flex items-center justify-center h-32 bg-gray-100 rounded text-gray-400 text-sm">Náhled není k dispozici</div>');
                            }),

                        View::make('mipress::filament.media.focal-point-editor')
                            ->visible(fn (): bool => $record->isImage() && ! $record->isSvg()),
                    ]),

                // Right: metadata + info
                Section::make('Metadata')
                    ->columnSpan(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('name')
                            ->label('Název souboru')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('alt')
                            ->label('Alt text')
                            ->helperText('Popis obrázku pro čtečky obrazovky a SEO.')
                            ->maxLength(255)
                            ->visible(fn (): bool => $record->isImage()),

                        Textarea::make('caption')
                            ->label('Popisek')
                            ->rows(2)
                            ->maxLength(1000),

                        Grid::make(2)->schema([
                            Placeholder::make('mime_type')
                                ->label('Typ souboru')
                                ->content(fn (): string => $record->mime_type ?? '—'),

                            Placeholder::make('size')
                                ->label('Velikost')
                                ->content(fn (): string => $record->getHumanReadableSize()),

                            Placeholder::make('created_at')
                                ->label('Nahráno')
                                ->content(fn (): string => $record->created_at?->format('j. n. Y H:i') ?? '—'),

                            Placeholder::make('conversions_info')
                                ->label('Konverze')
                                ->content(function () use ($record): string {
                                    $generated = $record->generated_conversions ?? [];

                                    if (empty($generated)) {
                                        return 'Žádné';
                                    }

                                    return collect($generated)
                                        ->filter(fn ($done) => $done)
                                        ->keys()
                                        ->join(', ');
                                }),
                        ]),
                    ]),
            ]),
        ]);
    }

    public function setFocalPoint(int $x, int $y): void
    {
        /** @var Media $record */
        $record = $this->getRecord();
        $record->focal_point = ['x' => $x, 'y' => $y];
        $record->save();

        $this->dispatch('focal-point-updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        /** @var Media $record */
        $record = $this->getRecord();

        return $record->name;
    }
}
