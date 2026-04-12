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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->nullable()->constrained('blueprints')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->json('data')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->foreignId('featured_image_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('locale')->default('cs');
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->migrateExistingPages();
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }

    private function migrateExistingPages(): void
    {
        $pagesCollectionId = DB::table('collections')
            ->where('handle', 'pages')
            ->value('id');

        if ($pagesCollectionId === null) {
            return;
        }

        $entries = DB::table('entries')
            ->where('collection_id', $pagesCollectionId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($entries as $entry) {
            DB::table('pages')->insert([
                'id' => $entry->id,
                'blueprint_id' => $entry->blueprint_id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'data' => $entry->data,
                'status' => $entry->status,
                'published_at' => $entry->published_at,
                'author_id' => $entry->author_id,
                'sort_order' => $entry->sort_order,
                'parent_id' => $entry->parent_id,
                'featured_image_id' => $entry->featured_image_id,
                'locale' => $entry->locale,
                'review_note' => $entry->review_note,
                'created_at' => $entry->created_at,
                'updated_at' => $entry->updated_at,
            ]);
        }
    }
};
