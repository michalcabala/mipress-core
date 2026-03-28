<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('alt')->nullable()->after('file_name');
            $table->string('caption', 1000)->nullable()->after('alt');
            $table->json('focal_point')->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn(['alt', 'caption', 'focal_point']);
        });
    }
};
