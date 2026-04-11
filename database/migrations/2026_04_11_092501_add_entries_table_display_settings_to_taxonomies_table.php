<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->string('entries_table_display_mode', 32)
                ->default('badges')
                ->after('collection_id');
            $table->string('entries_table_badge_palette', 32)
                ->default('neutral')
                ->after('entries_table_display_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table) {
            $table->dropColumn([
                'entries_table_display_mode',
                'entries_table_badge_palette',
            ]);
        });
    }
};
