<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table): void {
            if (! Schema::hasColumn('collections', 'hierarchical')) {
                $table->boolean('hierarchical')->default(false)->after('slugs');
            }
        });

        Schema::table('entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('entries', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('sort_order')->constrained('entries')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            if (Schema::hasColumn('entries', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });

        Schema::table('collections', function (Blueprint $table): void {
            if (Schema::hasColumn('collections', 'hierarchical')) {
                $table->dropColumn('hierarchical');
            }
        });
    }
};
