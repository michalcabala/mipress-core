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

    public function getHeaderActions(): array
    {
        return [
            Action::make('regenerate_all_curations')
                ->label('Přegenerovat všechny ořezy')
                ->icon('far-arrows-rotate')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Přegenerovat všechny ořezy')
                ->modalDescription('Ořezy všech obrázků budou přegenerovány podle jejich ohniskových bodů. Tato operace může chvíli trvat.')
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
                        ->title("Ořezy přegenerovány pro {$count} médií")
                        ->success()
                        ->send();
                }),
            Action::make('cleanup_unused_files')
                ->label('Smazat nepoužívané soubory')
                ->icon('far-broom')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Smazat nepoužívané soubory')
                ->modalDescription('Budou smazány soubory ve storage, které nemají odpovídající záznam v databázi, a dočasné soubory. Tato akce je nevratná.')
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
            ->title('Úklid dokončen')
            ->body("Smazáno {$deleted} nepoužívaných souborů a {$tmpDeleted} dočasných souborů.")
            ->success()
            ->send();
    }
