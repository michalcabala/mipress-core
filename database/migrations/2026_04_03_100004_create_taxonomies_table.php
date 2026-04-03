<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomies', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('handle')->unique();
            $table->boolean('is_hierarchical')->default(false);
            $table->foreignId('blueprint_id')->nullable()->constrained('blueprints')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomies');
    }
};
