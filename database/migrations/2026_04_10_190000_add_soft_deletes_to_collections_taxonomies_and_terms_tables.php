<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('collections', 'deleted_at')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('taxonomies', 'deleted_at')) {
            Schema::table('taxonomies', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('terms', 'deleted_at')) {
            Schema::table('terms', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('collections', 'deleted_at')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('taxonomies', 'deleted_at')) {
            Schema::table('taxonomies', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('terms', 'deleted_at')) {
            Schema::table('terms', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
