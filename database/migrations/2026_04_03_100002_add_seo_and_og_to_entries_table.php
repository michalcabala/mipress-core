<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->string('meta_title')->nullable()->after('review_note');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->foreignId('og_image_id')->nullable()->after('meta_description')->constrained('curator')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
        });

        // Migrate SEO data from data JSON column to real columns
        DB::table('entries')
            ->whereNotNull('data')
            ->where('data', '!=', 'null')
            ->where('data', '!=', '[]')
            ->where('data', '!=', '{}')
            ->get(['id', 'data'])
            ->each(function (object $entry): void {
                $data = json_decode((string) $entry->data, true);

                if (! is_array($data)) {
                    return;
                }

                $metaTitle = $data['meta_title'] ?? null;
                $metaDesc = $data['meta_description'] ?? null;

                if ($metaTitle !== null || $metaDesc !== null) {
                    DB::table('entries')->where('id', $entry->id)->update([
                        'meta_title' => is_string($metaTitle) ? $metaTitle : null,
                        'meta_description' => is_string($metaDesc) ? $metaDesc : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('og_image_id');
            $table->dropColumn(['meta_title', 'meta_description', 'scheduled_at']);
        });
    }
};
