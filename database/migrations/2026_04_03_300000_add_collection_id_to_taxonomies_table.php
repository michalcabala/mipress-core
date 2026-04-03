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
        Schema::table('taxonomies', function (Blueprint $table): void {
            $table->foreignId('collection_id')
                ->nullable()
                ->after('description')
                ->constrained('collections')
                ->nullOnDelete();
        });

        // Migrate data from pivot table
        DB::table('collection_taxonomy')
            ->orderBy('collection_id')
            ->each(function (object $pivot): void {
                DB::table('taxonomies')
                    ->where('id', $pivot->taxonomy_id)
                    ->whereNull('collection_id')
                    ->update(['collection_id' => $pivot->collection_id]);
            });
    }

    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collection_id');
        });
    }
};
