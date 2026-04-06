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
        $legacyRows = [];

        if (Schema::hasTable('settings')) {
            if (Schema::hasColumn('settings', 'key') && Schema::hasColumn('settings', 'value')) {
                $legacyRows = DB::table('settings')->select('key', 'value')->get()->all();
            }

            Schema::drop('settings');
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('name');
            $table->foreignId('blueprint_id')->nullable()->constrained('blueprints')->nullOnDelete();
            $table->json('data')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        if ($legacyRows === []) {
            return;
        }

        $grouped = [];

        foreach ($legacyRows as $row) {
            $fullKey = (string) ($row->key ?? '');
            $value = $row->value;

            if ($fullKey === '') {
                continue;
            }

            [$handle, $key] = str_contains($fullKey, '.')
                ? explode('.', $fullKey, 2)
                : ['legacy', $fullKey];

            $grouped[$handle] ??= [
                'handle' => $handle,
                'name' => str($handle)->replace('_', ' ')->headline()->toString(),
                'blueprint_id' => null,
                'data' => [],
                'icon' => 'fal-gear',
                'sort_order' => 999,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            data_set($grouped[$handle]['data'], $key, $value);
        }

        foreach ($grouped as $payload) {
            DB::table('settings')->insert([
                ...$payload,
                'data' => json_encode($payload['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
};
