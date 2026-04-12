<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            if (! Schema::hasColumn('media', 'focal_point_x')) {
                $table->unsignedTinyInteger('focal_point_x')
                    ->default(50)
                    ->after('order_column');
            }

            if (! Schema::hasColumn('media', 'focal_point_y')) {
                $table->unsignedTinyInteger('focal_point_y')
                    ->default(50)
                    ->after('focal_point_x');
            }

            if (! Schema::hasColumn('media', 'uploaded_by')) {
                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->after('focal_point_y')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        Schema::table('media', function (Blueprint $table): void {
            if (Schema::hasColumn('media', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }

            if (Schema::hasColumn('media', 'focal_point_y')) {
                $table->dropColumn('focal_point_y');
            }

            if (Schema::hasColumn('media', 'focal_point_x')) {
                $table->dropColumn('focal_point_x');
            }
        });
    }
};
