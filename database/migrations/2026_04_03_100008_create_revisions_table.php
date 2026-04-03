<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->morphs('revisionable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data');
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
