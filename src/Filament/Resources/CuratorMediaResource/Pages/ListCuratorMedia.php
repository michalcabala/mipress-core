<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\CuratorMediaResource\Pages;

use Awcodes\Curator\Resources\Media\Pages\ListMedia;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Filament\Resources\CuratorMediaResource;
use MiPress\Core\Models\CuratorMedia;
use MiPress\Core\Services\FocalPointCropper;

class ListCuratorMedia extends ListMedia
{
    protected static string $resource = CuratorMediaResource::class;

    public string $gridDensity = 'normal';

    public function getHeaderActions(): array
    {
        return [
            Action::make('toggle-grid-density')
                ->color('gray')
                ->label(fn (): string => $this->gridDensity === 'normal' ? __('mipress::admin.curator_media.actions.toggle_dense_grid') : __('mipress::admin.curator_media.actions.toggle_normal_grid'))
                ->icon(fn (): string => $this->gridDensity === 'normal' ? 'far-grid' : 'far-grid-2')
                ->visible(fn (): bool => $this->layoutView === 'grid')
                ->action(function (): void {
                    $this->gridDensity = $this->gridDensity === 'normal' ? 'compact' : 'normal';
                    $this->dispatch('layoutViewChanged', $this->layoutView);
                }),
            Action::make('regenerate_all_curations')
                ->label(__('mipress::admin.curator_media.actions.regenerate_all'))
                ->icon('far-arrows-rotate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('mipress::admin.curator_media.actions.regenerate_all'))
                ->modalDescription(__('mipress::admin.curator_media.actions.regenerate_all_modal_description'))
                ->action(function (): void {
                    $cropper = app(FocalPointCropper::class);
                    $count = 0;

                    CuratorMedia::query()
                        ->whereNotNull('ext')
                        ->each(function (CuratorMedia $media) use ($cropper, &$count): void {
                            if (! is_media_resizable($media->ext)) {
                                return;
                            }

                            $curations = $cropper->generateAll($media);
                            $media->update(['curations' => $curations]);
                            $count++;
                        });

                    Notification::make()
                        ->title(__('mipress::admin.curator_media.actions.regenerated_all_title', ['count' => $count]))
                        ->success()
                        ->send();
                }),
            Action::make('cleanup_unused_files')
                ->label(__('mipress::admin.curator_media.actions.cleanup'))
                ->icon('far-broom')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('mipress::admin.curator_media.actions.cleanup'))
                ->modalDescription(__('mipress::admin.curator_media.actions.cleanup_modal_description'))
                ->action(function (): void {
                    $this->cleanupOrphanedFiles();
                }),
            ...parent::getHeaderActions(),
        ];
    }

    private function cleanupOrphanedFiles(): void
    {
        $disk = config('curator.default_disk', 'local_uploads');
        $storage = Storage::disk($disk);

        $knownPaths = CuratorMedia::query()
            ->pluck('path')
            ->toArray();

        $curationPaths = CuratorMedia::query()
            ->whereNotNull('curations')
            ->pluck('curations')
            ->flatMap(fn (array $curations): array => collect($curations)
                ->pluck('curation.path')
                ->filter()
                ->all()
            )
            ->toArray();

        $allKnownPaths = array_merge($knownPaths, $curationPaths);

        // Also keep _original backup files
        $originalBackupPaths = [];
        foreach ($knownPaths as $path) {
            $dir = pathinfo($path, PATHINFO_DIRNAME);
            $name = pathinfo($path, PATHINFO_FILENAME);
            foreach (['jpg', 'jpeg', 'png', 'bmp'] as $ext) {
                $originalBackupPaths[] = $dir.'/'.$name.'_original.'.$ext;
            }
        }

        $allKnownPaths = array_merge($allKnownPaths, $originalBackupPaths);

        $allFiles = $storage->allFiles('curatormedia');
        $deleted = 0;

        foreach ($allFiles as $file) {
            if (! in_array($file, $allKnownPaths, true)) {
                $storage->delete($file);
                $deleted++;
            }
        }

        // Clean up empty directories
        foreach (array_reverse($storage->allDirectories('curatormedia')) as $dir) {
            if (count($storage->allFiles($dir)) === 0) {
                $storage->deleteDirectory($dir);
            }
        }

        // Clean up livewire temp files
        $tmpDeleted = 0;
        foreach ($storage->allFiles('livewire-tmp') as $file) {
            if ($storage->lastModified($file) < now()->subHours(24)->getTimestamp()) {
                $storage->delete($file);
                $tmpDeleted++;
            }
        }

        Notification::make()
            ->title(__('mipress::admin.curator_media.actions.cleanup_done_title'))
            ->body(__('mipress::admin.curator_media.actions.cleanup_done_body', ['deleted' => $deleted, 'tmp' => $tmpDeleted]))
            ->success()
            ->send();
    }
}
