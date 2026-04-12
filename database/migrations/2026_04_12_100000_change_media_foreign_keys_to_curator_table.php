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
        // Null out any orphaned references that point to old media table IDs not present in curator
        DB::table('entries')
            ->whereNotNull('featured_image_id')
            ->whereNotIn('featured_image_id', DB::table('curator')->select('id'))
            ->update(['featured_image_id' => null]);

        DB::table('entries')
            ->whereNotNull('og_image_id')
            ->whereNotIn('og_image_id', DB::table('curator')->select('id'))
            ->update(['og_image_id' => null]);

        DB::table('pages')
            ->whereNotNull('featured_image_id')
            ->whereNotIn('featured_image_id', DB::table('curator')->select('id'))
            ->update(['featured_image_id' => null]);

        // Drop old FKs (skip if already dropped by a partial run)
        Schema::table('entries', function (Blueprint $table): void {
            if ($this->hasForeignKey('entries', 'entries_featured_image_id_foreign')) {
                $table->dropForeign(['featured_image_id']);
            }

            $table->foreign('featured_image_id')->references('id')->on('curator')->nullOnDelete();
        });

        Schema::table('entries', function (Blueprint $table): void {
            if ($this->hasForeignKey('entries', 'entries_og_image_id_foreign')) {
                $table->dropForeign(['og_image_id']);
            }

            $table->foreign('og_image_id')->references('id')->on('curator')->nullOnDelete();
        });

        Schema::table('pages', function (Blueprint $table): void {
            if ($this->hasForeignKey('pages', 'pages_featured_image_id_foreign')) {
                $table->dropForeign(['featured_image_id']);
            }

            $table->foreign('featured_image_id')->references('id')->on('curator')->nullOnDelete();
        });
    }

    private function hasForeignKey(string $table, string $constraintName): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [DB::getDatabaseName(), $table, $constraintName, 'FOREIGN KEY'],
        );
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropForeign(['featured_image_id']);
            $table->foreign('featured_image_id')->references('id')->on('media')->nullOnDelete();
        });

        Schema::table('entries', function (Blueprint $table): void {
            $table->dropForeign(['og_image_id']);
            $table->foreign('og_image_id')->references('id')->on('media')->nullOnDelete();
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->dropForeign(['featured_image_id']);
            $table->foreign('featured_image_id')->references('id')->on('media')->nullOnDelete();
        });
    }
};
