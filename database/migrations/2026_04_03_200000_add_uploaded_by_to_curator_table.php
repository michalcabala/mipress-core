<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->foreignId('uploaded_by')
                ->nullable()
                ->after('tenant_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curator', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\User::class, 'uploaded_by');
            $table->dropColumn('uploaded_by');
        });
    }
};
