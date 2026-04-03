<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->json('data')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('origin_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->string('locale')->default('cs');
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
