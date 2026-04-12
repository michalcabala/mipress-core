<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use MiPress\Core\Jobs\RegenerateMediaConversionsJob;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Media;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

class MediaFileService
{
    public function createEditorCopy(Media $record, string $prefix): ?string
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk(MediaConfig::disk());
        $originalPath = ltrim((string) $record->getPathRelativeToRoot(), '/');

        if (! $storage->exists($originalPath)) {
            return null;
        }

        $tmpName = $prefix.'_'.uniqid().'_'.basename($record->file_name);
        $tmpPath = 'tmp/resource/'.$tmpName;

        if (! $storage->copy($originalPath, $tmpPath)) {
            return null;
        }

        return $tmpPath;
    }

    public function extractUploadPath(mixed $state): ?string
    {
        if (is_string($state)) {
            $state = trim($state);

            return $state !== '' ? $state : null;
        }

        if (is_array($state)) {
            $first = reset($state);

            return $this->extractUploadPath($first === false ? null : $first);
        }

        return null;
    }

    public function replaceOriginal(Media $record, string $temporaryPath): bool
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk(MediaConfig::disk());

        if (! $storage->exists($temporaryPath)) {
            return false;
        }

        $currentRelativePath = ltrim((string) $record->getPathRelativeToRoot(), '/');
        $targetDirectory = trim((string) dirname($currentRelativePath), '/');
        $newFileName = basename($temporaryPath);

        if ($newFileName === '' || $newFileName === '.') {
            return false;
        }

        $targetRelativePath = $targetDirectory !== ''
            ? $targetDirectory.'/'.$newFileName
            : $newFileName;

        if ($targetDirectory !== '' && ! $storage->exists($targetDirectory)) {
            $storage->makeDirectory($targetDirectory);
        }

        if ($storage->exists($targetRelativePath)) {
            $storage->delete($targetRelativePath);
        }

        if (! $storage->move($temporaryPath, $targetRelativePath)) {
            return false;
        }

        if ($currentRelativePath !== $targetRelativePath && $storage->exists($currentRelativePath)) {
            $storage->delete($currentRelativePath);
        }

        $record->forceFill([
            'name' => pathinfo($newFileName, PATHINFO_FILENAME),
            'file_name' => $newFileName,
            'mime_type' => (string) ($storage->mimeType($targetRelativePath) ?: $record->mime_type),
            'size' => (int) ($storage->size($targetRelativePath) ?: $record->size),
        ])->saveQuietly();

        $dimensions = @getimagesize($record->getPath());

        if (is_array($dimensions) && isset($dimensions[0], $dimensions[1])) {
            $record->setCustomProperty('width', (int) $dimensions[0]);
            $record->setCustomProperty('height', (int) $dimensions[1]);
            $record->saveQuietly();
        }

        $this->invalidateManualOverrides($record);

        RegenerateMediaConversionsJob::dispatch([(int) $record->getKey()]);

        return true;
    }

    public function replaceConversion(Media $record, string $conversionName, string $temporaryPath): bool
    {
        $conversion = MediaConfig::findConversion($conversionName);

        if ($conversion === null) {
            return false;
        }

        $targetWidth = (int) ($conversion['w'] ?? 0);
        $targetHeight = (int) ($conversion['h'] ?? 0);

        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return false;
        }

        $storage = Storage::disk(MediaConfig::disk());

        if (! $storage->exists($temporaryPath)) {
            return false;
        }

        $targetRelativePath = ltrim((string) $record->getPathRelativeToRoot($conversionName), '/');

        if ($targetRelativePath === '') {
            return false;
        }

        $targetDirectory = trim((string) dirname($targetRelativePath), '/');

        if ($targetDirectory !== '' && ! $storage->exists($targetDirectory)) {
            $storage->makeDirectory($targetDirectory);
        }

        try {
            Image::load($storage->path($temporaryPath))
                ->fit(Fit::Crop, $targetWidth, $targetHeight)
                ->format('webp')
                ->quality(MediaConfig::conversionQuality())
                ->save($storage->path($targetRelativePath));
        } catch (Throwable) {
            return false;
        } finally {
            if ($storage->exists($temporaryPath)) {
                $storage->delete($temporaryPath);
            }
        }

        $record->markAsConversionGenerated($conversionName);
        $record->markManualConversionOverride($conversionName);

        return true;
    }

    public function invalidateManualOverrides(Media $record): void
    {
        $overrides = $record->manualConversionOverrides();

        if ($overrides === []) {
            return;
        }

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk(MediaConfig::disk());
        $generatedConversions = is_array($record->generated_conversions) ? $record->generated_conversions : [];

        foreach (array_keys($overrides) as $conversionName) {
            $conversionPath = ltrim((string) $record->getPathRelativeToRoot($conversionName), '/');

            if ($conversionPath !== '' && $storage->exists($conversionPath)) {
                $storage->delete($conversionPath);
            }

            $generatedConversions[$conversionName] = false;
        }

        $record->generated_conversions = $generatedConversions;
        $record->setCustomProperty('manual_conversion_overrides', []);
        $record->saveQuietly();
    }

    /**
     * @return array<int, string|null>
     */
    public function cropperAspectRatioOptions(): array
    {
        $options = [null];

        foreach (MediaConfig::editorCropConversions() as $conversion) {
            $width = (int) ($conversion['w'] ?? 0);
            $height = (int) ($conversion['h'] ?? 0);

            if ($width > 0 && $height > 0) {
                $options[] = $width.':'.$height;
            }
        }

        return array_values(array_unique($options));
    }
}
