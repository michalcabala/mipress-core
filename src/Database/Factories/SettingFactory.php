<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Setting;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        return [
            'handle' => $this->faker->unique()->slug(2, '_'),
            'name' => $this->faker->words(2, true),
            'blueprint_id' => Blueprint::factory(),
            'data' => [],
            'icon' => 'fal-gear',
            'sort_order' => 0,
        ];
    }
}
