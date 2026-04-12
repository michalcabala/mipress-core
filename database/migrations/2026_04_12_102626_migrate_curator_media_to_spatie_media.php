<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Models\Attachment;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('curator') || ! Schema::hasTable('media') || ! Schema::hasTable('attachments')) {
            return;
        }

        $legacyMediaItems = DB::table('curator')
            ->orderBy('id')
            ->get();

        foreach ($legacyMediaItems as $legacyMedia) {
            if (DB::table('media')->where('id', $legacyMedia->id)->exists()) {
                continue;
            }

            $attachmentId = DB::table('attachments')->insertGetId([
                'name' => $legacyMedia->pretty_name ?: $legacyMedia->name,
                'created_at' => $legacyMedia->created_at,
                'updated_at' => $legacyMedia->updated_at,
            ]);

            $fileName = basename((string) $legacyMedia->path);
            $relativePath = sprintf(
                '%s/%s/%s/%s',
                date('Y', strtotime((string) $legacyMedia->created_at ?: 'now')),
                date('m', strtotime((string) $legacyMedia->created_at ?: 'now')),
                $legacyMedia->id,
                $fileName,
            );

            DB::table('media')->insert([
                'id' => $legacyMedia->id,
                'model_type' => Attachment::class,
                'model_id' => $attachmentId,
                'uuid' => (string) Str::uuid(),
                'collection_name' => MediaConfig::libraryCollection(),
                'name' => $legacyMedia->name,
                'file_name' => $fileName,
                'mime_type' => $legacyMedia->type,
                'disk' => MediaConfig::disk(),
                'conversions_disk' => MediaConfig::disk(),
                'size' => (int) ($legacyMedia->size ?? 0),
                'manipulations' => json_encode([]),
                'custom_properties' => json_encode(array_filter([
                    'alt' => $legacyMedia->alt,
                    'title' => $legacyMedia->title,
                    'description' => $legacyMedia->description,
                    'caption' => $legacyMedia->caption,
                    'width' => $legacyMedia->width,
                    'height' => $legacyMedia->height,
                    'legacy_path' => $legacyMedia->path,
                ], fn (mixed $value): bool => $value !== null && $value !== '')),
                'generated_conversions' => json_encode([]),
                'responsive_images' => json_encode([]),
                'order_column' => $legacyMedia->id,
                'created_at' => $legacyMedia->created_at,
                'updated_at' => $legacyMedia->updated_at,
                'focal_point_x' => 50,
                'focal_point_y' => 50,
                'uploaded_by' => $legacyMedia->uploaded_by ?? null,
            ]);

            $this->moveLegacyFileToNewDisk(
                oldDisk: (string) $legacyMedia->disk,
                oldPath: (string) $legacyMedia->path,
                newPath: $relativePath,
            );
        }
    }

    public function down(): void
    {
        //
    }

    private function moveLegacyFileToNewDisk(string $oldDisk, string $oldPath, string $newPath): void
    {
        if (! config()->has("filesystems.disks.{$oldDisk}")) {
            return;
        }

        $sourceDisk = Storage::disk($oldDisk);
        $targetDisk = Storage::disk(MediaConfig::disk());

        if (! $sourceDisk->exists($oldPath) || $targetDisk->exists($newPath)) {
            return;
        }

        $targetDisk->put($newPath, $sourceDisk->get($oldPath), 'public');
    }
};
