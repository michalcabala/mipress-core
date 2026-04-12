<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewireColumn('entries', 'featured_image_id');
        $this->rewireColumn('entries', 'og_image_id');
        $this->rewireColumn('pages', 'featured_image_id');
        $this->rewireColumn('users', 'avatar_id');
    }

    public function down(): void
    {
        // Down migration intentionally omitted because Curator is being removed permanently.
    }

    private function rewireColumn(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            try {
                $table->dropForeign([$column]);
            } catch (Throwable) {
            }
        });

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->foreign($column)->references('id')->on('media')->nullOnDelete();
        });
    }
};
