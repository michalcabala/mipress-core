<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomies', function (Blueprint $table): void {
            $table->boolean('show_in_entries_table')->default(true);
            $table->boolean('show_in_entries_filter')->default(true);
            $table->boolean('searchable_in_entries_table')->default(false);
            $table->boolean('sortable_in_entries_table')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('taxonomies', function (Blueprint $table): void {
            $table->dropColumn([
                'show_in_entries_table',
                'show_in_entries_filter',
                'searchable_in_entries_table',
                'sortable_in_entries_table',
            ]);
        });
    }
};
