<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CuratorMediaResource\Schemas;

use Awcodes\Curator\Components\Forms\CuratorEditor;
use Awcodes\Curator\Components\Forms\Uploader;
use Awcodes\Curator\CuratorPlugin;
use Awcodes\Curator\Facades\Curator;
use Awcodes\Curator\Resources\Media\Schemas\MediaForm;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class CuratorMediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.file'))
                            ->hiddenOn('edit')
                            ->schema([
                                static::getUploaderField()
                                    ->required(),
                            ]),
                        Tabs::make('image')
                            ->hiddenOn('create')
                            ->tabs([
                                Tab::make(trans('curator::forms.sections.preview'))
                                    ->icon('far-eye')
                                    ->schema([
                                        ViewField::make('preview')
                                            ->view('curator::components.forms.preview')
                                            ->hiddenLabel()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (ViewField $component, $state, $record): void {
                                                $component->state($record);
                                            }),
                                    ]),
                                Tab::make('Ohniskový bod')
                                    ->icon('far-crosshairs')
                                    ->visible(fn ($record): bool => $record && is_media_resizable($record->ext))
                                    ->schema([
                                        ViewField::make('focal_point')
                                            ->view('mipress::curator.focal-point-picker')
                                            ->hiddenLabel()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (ViewField $component, $state, $record): void {
                                                $component->state($record);
                                            }),
                                        Hidden::make('focal_point_x'),
                                        Hidden::make('focal_point_y'),
                                    ]),
                                Tab::make(trans('curator::forms.sections.curation'))
                                    ->icon('far-crop')
                                    ->visible(fn ($record): bool => is_media_resizable($record->ext) && CuratorPlugin::get()->supportsCurations())
                                    ->schema([
                                        Repeater::make('curations')
                                            ->label(trans('curator::forms.sections.curation'))
                                            ->hiddenLabel()
                                            ->reorderable(false)
                                            ->itemLabel(fn ($state): ?string => $state['curation']['key'] ?? null)
                                            ->collapsible()
                                            ->schema([
                                                CuratorEditor::make('curation')
                                                    ->hiddenLabel()
                                                    ->buttonLabel(trans('curator::forms.curations.button_label'))
                                                    ->required()
                                                    ->lazy(),
                                            ]),
                                    ]),
                                Tab::make(trans('curator::forms.sections.replace'))
                                    ->icon('far-arrow-up-from-bracket')
                                    ->visible(fn () => CuratorPlugin::get()->supportsFileSwap())
                                    ->schema([
                                        static::getUploaderField()
                                            ->helperText(trans('curator::forms.sections.upload_new_helper')),
                                    ]),
                            ]),
                        Section::make(trans('curator::forms.sections.details'))
                            ->schema([
                                ViewField::make('details')
                                    ->view('curator::components.forms.details')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->columnSpan('full')
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        $component->state($record);
                                    }),
                            ]),
                    ])
                    ->columnSpan([
                        'md' => 'full',
                        'lg' => 2,
                    ]),
                Group::make()
                    ->schema([
                        Section::make(trans('curator::forms.sections.meta'))
                            ->schema(static::getAdditionalInformationFormSchema()),
                    ])->columnSpan([
                        'md' => 'full',
                        'lg' => 1,
                    ]),
            ])->columns([
                'lg' => 3,
            ]);
    }

    public static function getAdditionalInformationFormSchema(): array
    {
        return MediaForm::getAdditionalInformationFormSchema();
    }

    public static function getUploaderField(): Uploader
    {
        return Uploader::make('file')
            ->acceptedFileTypes(Curator::getAcceptedFileTypes())
            ->directory(Curator::getDirectory())
            ->disk(Curator::getDiskName())
            ->hiddenLabel()
            ->minSize(Curator::getMinSize())
            ->maxFiles(1)
            ->maxSize(Curator::getMaxSize())
            ->panelAspectRatio('24:9')
            ->preserveFilenames(Curator::shouldPreserveFilenames())
            ->visibility(Curator::getVisibility())
            ->storeFileNamesIn('originalFilename')
            ->imageCropAspectRatio(Curator::getImageCropAspectRatio())
            ->imageResizeMode(Curator::getImageResizeMode())
            ->imageResizeTargetWidth(Curator::getImageResizeTargetWidth())
            ->imageResizeTargetHeight(Curator::getImageResizeTargetHeight());
    }
}
