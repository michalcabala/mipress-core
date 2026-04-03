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
        Schema::table('pages', function (Blueprint $table): void {
            $table->json('content')->nullable()->after('data');
            $table->string('meta_title')->nullable()->after('content');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->timestamp('scheduled_at')->nullable()->after('published_at');
            $table->foreignId('origin_id')->nullable()->after('locale')->constrained('pages')->nullOnDelete();
        });

        // Migrate SEO data from data JSON column to real columns
        DB::table('pages')
            ->whereNotNull('data')
            ->where('data', '!=', 'null')
            ->where('data', '!=', '[]')
            ->where('data', '!=', '{}')
            ->get(['id', 'data'])
            ->each(function (object $page): void {
                $data = json_decode((string) $page->data, true);

                if (! is_array($data)) {
                    return;
                }

                $metaTitle = $data['meta_title'] ?? null;
                $metaDesc = $data['meta_description'] ?? null;

                if ($metaTitle !== null || $metaDesc !== null) {
                    DB::table('pages')->where('id', $page->id)->update([
                        'meta_title' => is_string($metaTitle) ? $metaTitle : null,
                        'meta_description' => is_string($metaDesc) ? $metaDesc : null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_id');
            $table->dropColumn(['content', 'meta_title', 'meta_description', 'scheduled_at']);
        });
    }
};
